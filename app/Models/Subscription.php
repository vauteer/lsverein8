<?php

namespace App\Models;

use App\AssignedMemberCount;
use App\Models\Scopes\ClubScope;
use App\Pdf\SepaPdf;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Stringable;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property float $amount
 * @property string|null $transfer_text
 * @property string|null $memo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read MemberSubscription|null $pivot the pivot row when loaded through Member::subscriptions()
 */
#[Fillable([
    'club_id',
    'name',
    'amount',
    'transfer_text',
    'memo',
])]
#[ScopedBy([ClubScope::class])]
class Subscription extends Model implements Stringable
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // decimal(8,2) comes back from MySQL as a string but from SQLite as a
        // float; the cast keeps `@property float $amount` honest on both, and
        // keeps the JSON the index sends numeric.
        return [
            'amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsToMany<Member, $this, MemberSubscription>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['memo'])
            ->withTimestamps()
            ->using(MemberSubscription::class);
    }

    /**
     * Count the members who currently hold this subscription.
     *
     * Mirrors the `subscription_X` selection (`members()->hasSubscription()`),
     * so the number and what it links to cannot disagree. `member_subscription`
     * carries no dates, so "current" can only mean a current member: living,
     * with an open club membership.
     *
     * A plain `withCount('members')` counted everyone who ever held it,
     * including the long departed — Erwachsen read 242 where the selection
     * shows 140, which also overstates what the club actually collects.
     *
     * @param  Builder<Subscription>  $query
     */
    #[Scope]
    protected function withCurrentMemberCount(Builder $query): void
    {
        $query->addSelect([
            'members_count' => AssignedMemberCount::held(
                'subscriptions', 'member_subscription', 'subscription_id'
            ),
        ]);
    }

    public function isUsed(): bool
    {
        return DB::table('member_subscription')->where('subscription_id', $this->id)->exists();
    }

    /**
     * The amount as it is shown to the user, e.g. "1.234,50 €".
     */
    public function amountLabel(): string
    {
        return formatAmount($this->amount);
    }

    public function __toString(): string
    {
        return "{$this->name} ({$this->amountLabel()})";
    }

    /**
     * Splits the members holding one of the given subscriptions into direct debits
     * and outstanding payments, then generates the SEPA files for the debits.
     *
     * @param  list<int>  $subscriptions
     * @return array{outStandings: array<int, array{name: string, subscription: string, paymentMethod: string}>, downloads: array<int, array{name: string, href: string}>}
     */
    public static function debit(array $subscriptions, CarbonInterface $executionDate): array
    {
        $debits = [];
        $outStandings = [];

        $members = Member::members()
            ->hasSubscription($subscriptions)
            ->orderBy('surname')->orderBy('first_name')
            ->get();

        foreach ($members as $member) {
            foreach ($member->subscriptions as $subscription) {
                if (! in_array($subscription->id, $subscriptions)) {
                    continue;
                }

                if ($member->payment_method->isCollectable()) {
                    $debits[] = [
                        'member_id' => $member->id,
                        'amount' => $subscription->amount,
                        'transfer_text' => $subscription->transfer_text,
                    ];
                } else {
                    $outStandings[] = [
                        'name' => $member->first_name.' '.$member->surname,
                        'subscription' => $subscription->__toString(),
                        'paymentMethod' => $member->payment_method->label(),
                    ];
                }
            }
        }

        return [
            'outStandings' => $outStandings,
            'downloads' => self::generateSepa($debits, $executionDate),
        ];
    }

    /**
     * Writes the SEPA XML file and its PDF cover sheet to storage/downloads.
     *
     * @param  array<int, array{member_id: int, amount: float, transfer_text: string}>  $debits
     * @return array<int, array{name: string, href: string}>
     */
    public static function generateSepa(array $debits, CarbonInterface $executionDate): array
    {
        $creationDate = now();
        $year = $executionDate->year;
        $club = currentClub();
        $defaultDate = $club->sepa_mandate_date;
        $payments = [];
        $totalAmount = 0.0;
        $data['msgId'] = 'M'.$creationDate->format('YmdHis');
        $data['pmtInfId'] = 'P'.$creationDate->format('YmdHis');
        $data['creDtTm'] = substr($creationDate->toISO8601String(), 0, 19);
        $data['reqdColltnDt'] = $executionDate->format('Y-m-d');
        $data['nm'] = $club->name;
        $data['iban'] = str_replace(' ', '', $club->iban);
        $data['bic'] = $club->bic;
        $data['sepaId'] = str_replace(' ', '', $club->sepa_creditor_id);

        foreach ($debits as $debit) {
            $member = Member::find($debit['member_id']);
            $totalAmount += $debit['amount'];
            $transferText = str_replace(['<AJ>', '<VN>', '<NN>'], [(string) $year, $member->first_name, $member->surname],
                $debit['transfer_text']);
            $dateOfSignature = $defaultDate->max($member->entry());

            $payments[] = [
                'nm' => $member->account_owner,
                'iban' => str_replace(' ', '', $member->iban),
                'bic' => $member->bic,
                'amount' => $debit['amount'],
                'instdAmt' => sprintf('%01.2f', $debit['amount']),
                'ustrd' => $transferText,
                'mndtId' => $member->member_id,
                'dtOfSgntr' => $dateOfSignature->format('Y-m-d'),
            ];
        }

        $data['nbOfTxs'] = count($payments);
        $data['ctrlSum'] = sprintf('%01.2f', $totalAmount);
        $data['payments'] = $payments;

        // storage/downloads is not in version control and is wiped by a
        // deploy, so it may simply not be there the first time a club
        // collects. file_put_contents() below would fatal on that.
        File::ensureDirectoryExists(storage_path('downloads'));

        $sepaName = 'sepa.xml';
        $sepaPath = storage_path("downloads/{$club->id}_".$sepaName);
        $data['header'] = '<?xml version="1.0" encoding="utf-8"?>'; // <? wuerde in view als PHP gewertet!
        $sepaData = view('sepaxml', $data)->render();

        file_put_contents($sepaPath, $sepaData);

        $pdf = new SepaPdf;

        $pdfName = 'Abbuchungen.pdf';
        $pdfPath = storage_path("downloads/{$club->id}_".$pdfName);
        file_put_contents($pdfPath, $pdf->getOutput($payments, 'Sepa-Bankeinzug', $club->name));

        return [
            0 => ['name' => 'Sepa-Datei', 'href' => "/downloads/{$sepaName}"],
            1 => ['name' => 'Begleitzettel', 'href' => "/downloads/{$pdfName}"],
        ];
    }
}

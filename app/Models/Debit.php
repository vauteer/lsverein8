<?php

namespace App\Models;

use App\Models\Scopes\MemberClubScope;
use Carbon\CarbonInterface;
use Database\Factories\DebitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stringable;

/**
 * @property int $id
 * @property int $member_id
 * @property float $amount
 * @property string $transfer_text
 * @property CarbonInterface $due_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'member_id',
    'amount',
    'transfer_text',
    'due_at',
])]
#[ScopedBy([MemberClubScope::class])]
class Debit extends Model implements Stringable
{
    /** @use HasFactory<DebitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // decimal(8,2) comes back from MySQL as a string but from SQLite
            // as a float, same as subscriptions.amount.
            'amount' => 'float',
            'due_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
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
        return "{$this->transfer_text} ({$this->amountLabel()})";
    }

    /**
     * @param  Builder<Debit>  $query
     */
    #[Scope]
    protected function due(Builder $query, ?CarbonInterface $date): void
    {
        $query->where('due_at', '<=', $date ?? now()->endOfDay());
    }

    /**
     * Collects every debit due on the execution date, hands them to the SEPA
     * generator and clears them.
     *
     * MemberClubScope keeps both queries inside the acting club, so a
     * collection never reaches — or deletes — another club's debits.
     *
     * @return array{downloads: array<int, array{name: string, href: string}>}
     */
    public static function debit(CarbonInterface $executionDate): array
    {
        $debits = Debit::query()->due($executionDate)->get()
            ->map(fn (Debit $debit): array => [
                'member_id' => $debit->member_id,
                'amount' => $debit->amount,
                'transfer_text' => $debit->transfer_text,
            ])
            ->all();

        Debit::query()->due($executionDate)->delete();

        return [
            'downloads' => Subscription::generateSepa($debits, $executionDate),
        ];
    }
}

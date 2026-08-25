<?php

namespace App\Models;

use App\Models\Scopes\ClubScope;
use App\Pdf\BlsvPdf;
use Carbon\CarbonInterface;
use Database\Factories\ClubFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $street
 * @property string $zipcode
 * @property string $city
 * @property bool $blsv_member
 * @property string $bank
 * @property string $account_owner
 * @property string $iban
 * @property string $bic
 * @property string|null $sepa
 * @property CarbonInterface|null $sepa_date
 * @property string|null $logo
 * @property int $display
 * @property string $locale
 * @property string|null $honor_years
 * @property bool $use_items
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read ClubMember|null $pivot the pivot row when loaded through Member::memberships()
 */
#[Fillable([
    'name',
    'street',
    'zipcode',
    'city',
    'blsv_member',
    'bank',
    'account_owner',
    'iban',
    'bic',
    'sepa',
    'sepa_date',
    'logo',
    'display',
    'locale',
    'honor_years',
    'use_items',
])]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blsv_member' => 'boolean',
            'sepa_date' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Member, $this, ClubMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['from', 'to', 'memo'])
            ->withTimestamps()
            ->withoutGlobalScope(ClubScope::class)
            ->using(ClubMember::class);
    }

    /**
     * @return BelongsToMany<User, $this, ClubUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role'])
            ->withTimestamps()
            ->using(ClubUser::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Whether anything still hangs off this club. Nine tables reference
     * `clubs`, mostly ON DELETE CASCADE, so deleting a club that is still in
     * use would silently take its members, sections, honours, roles and
     * subscriptions with it. ClubPolicy::delete() refuses while this is true.
     */
    public function isUsed(): bool
    {
        // withoutGlobalScope: Subscription carries ClubScope, which would
        // narrow this to the *acting* club rather than the one being checked,
        // and so report another club's subscriptions as absent. members()
        // already drops the scope in the relation itself.
        return $this->members()->exists()
            || $this->users()->exists()
            || $this->subscriptions()->withoutGlobalScope(ClubScope::class)->exists();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function usedSections(): Collection
    {
        return DB::table('club_member')
            ->join('member_section', 'club_member.member_id', 'member_section.member_id')
            ->join('sections', 'sections.id', 'member_section.section_id')
            ->distinct()
            ->select('section_id as id', 'name')
            ->where('club_member.club_id', $this->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * The "public" disk, on which club logos are stored - resolved lazily
     * (not cached in a static) so tests can swap in Storage::fake().
     */
    public static function logoDisk(): Filesystem
    {
        return Storage::disk('public');
    }

    public static function logoStoragePath(string $filename): string
    {
        return 'logo/'.trim($filename, '/');
    }

    /**
     * The URL of the club's logo, or null when it has none. Callers render
     * their own placeholder - lsverein7 pointed at an images/no_logo.png
     * that this app does not ship.
     */
    public function logoURL(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return self::logoDisk()->exists(self::logoStoragePath($this->logo))
            ? self::logoDisk()->url(self::logoStoragePath($this->logo))
            : null;
    }

    public static function removeOrphanLogos(): int
    {
        $count = 0;
        $disk = self::logoDisk();

        foreach ($disk->files('logo') as $path) {
            // Skip dotfiles: storage/app/public/logo/.gitignore is what keeps
            // the directory in the repository, and nothing references it, so
            // an unfiltered sweep deletes it on the first run.
            if (str_starts_with(basename($path), '.')) {
                continue;
            }

            if (static::where('logo', basename($path))->first() === null) {
                $disk->delete($path);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, string>
     */
    public static function displayStyles(): array
    {
        return [
            '1' => 'Logo + Name',
            '2' => 'Logo',
            '3' => 'Name',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return [
            'de' => 'Deutsch',
            'en' => 'English',
        ];
    }

    /**
     * @return non-empty-list<array{m: int, w: int}>
     */
    private static function getBlankStat(): array
    {
        return [
            ['m' => 0, 'w' => 0],
            ['m' => 0, 'w' => 0],
            ['m' => 0, 'w' => 0],
            ['m' => 0, 'w' => 0],
            ['m' => 0, 'w' => 0],
            ['m' => 0, 'w' => 0],
            ['m' => 0, 'w' => 0],
        ];
    }

    private static function getStatIndex(int $age): int
    {
        return match (true) {
            $age < 6 => 0,
            $age < 14 => 1,
            $age < 18 => 2,
            $age < 27 => 3,
            $age < 41 => 4,
            $age < 61 => 5,
            default => 6
        };
    }

    /**
     * Builds the yearly BLSV age statistic, writes one CSV per section plus a
     * summary CSV and PDF to storage/downloads.
     *
     * @return array<int, array{name: string, href: string}>
     */
    public function getBLSVStatistic(): array
    {
        // Statistik ist zum 1. Januar, Austritte zum 31.12. und Eintritte zum 1.1. werden realisiert
        $keyDate = now()->startOfYear();
        Member::$_keyDate = $keyDate;
        $year = $keyDate->year;

        $files = [];
        $stats = [-1 => self::getBlankStat()];
        $totals = self::getBlankStat();

        $members = Member::members()
            ->orderBy('surname')->orderBy('first_name')
            ->get();

        foreach ($members as $member) {
            $gender = ($member->gender->value === 'm') ? 'm' : 'w';
            $index = self::getStatIndex($member->age);
            $row = $totals[$index];
            $row[$gender]++;
            $totals[$index] = $row;
        }

        $stats[-1] = $totals;

        $totalCsv = "Titel;Name;Vorname;Namenszusatz;Geschlecht;Geburtsdatum;Spartennummer\r\n";

        foreach (Section::whereNotNull('blsv_id')->orderBy('blsv_id')->get() as $section) {
            $csv = null;
            $stat = self::getBlankStat();
            $count = 0;

            $members = Member::members()->inBlsvSections($section->blsv_id)
                ->orderBy('surname')->orderBy('first_name')
                ->get();

            foreach ($members as $member) {
                $gender = $member->gender->value === 'm' ? 'm' : 'w';
                $line = ';'.mb_convert_encoding($member->surname, 'ISO-8859-1', 'UTF-8').';'.
                    mb_convert_encoding($member->first_name, 'ISO-8859-1', 'UTF-8').';;'.
                    $gender.';'.
                    '"'.$member->birthday->format('d.m.y').'";'.
                    $section->blsv_id."\r\n";

                $csv .= $line;
                $totalCsv .= $line;

                $index = self::getStatIndex($member->age);
                $row = $stat[$index];
                $row[$gender]++;
                $stat[$index] = $row;
                $count++;
            }

            if ($count > 0) {
                $stats[$section->blsv_id] = $stat + ['name' => $section->name];
                $filename = "BE{$year}_{$section->name}.csv";
                file_put_contents(storage_path("downloads/{$this->id}_".$filename), $csv);

                $files[] = ['name' => $section->name, 'href' => "/downloads/{$filename}"];
            }
        }

        $filename = "BE{$year}_Gesamt.csv";
        file_put_contents(storage_path("downloads/{$this->id}_".$filename), $totalCsv);
        $files[] = ['name' => 'Alle Sparten', 'href' => "/downloads/{$filename}"];

        $pdf = new BlsvPdf;
        $filename = 'blsv_stat.pdf';
        file_put_contents(
            storage_path("downloads/{$this->id}_".$filename),
            $pdf->getOutput($stats, $keyDate, $this->name)
        );
        $files[] = ['name' => 'Alters-Statistik', 'href' => "/downloads/{$filename}"];

        return array_reverse($files);
    }

    public function calcBlsvDebit(float $childrenDue, float $teenDue, float $adultDue): float
    {
        $children = Member::members()->ageRange(null, 13)->count();
        $teens = Member::members()->ageRange(14, 17)->count();
        $adults = Member::members()->ageRange(18, null)->count();

        return ($children * $childrenDue) + ($teens * $teenDue) + ($adults * $adultDue);
    }
}

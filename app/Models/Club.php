<?php

namespace App\Models;

use App\AssignedMemberCount;
use App\BlsvMemberReport;
use App\Enums\AgeBracket;
use App\Enums\ClubIdentityDisplay;
use App\Enums\Locale;
use App\Models\Scopes\ClubScope;
use App\Pdf\BlsvPdf;
use Carbon\CarbonInterface;
use Database\Factories\ClubFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
 * @property string|null $sepa_creditor_id
 * @property CarbonInterface|null $sepa_mandate_date
 * @property string|null $logo
 * @property ClubIdentityDisplay $identity_display
 * @property Locale $locale
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
    'sepa_creditor_id',
    'sepa_mandate_date',
    'logo',
    'identity_display',
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
            'sepa_mandate_date' => 'datetime',
            'identity_display' => ClubIdentityDisplay::class,
            'locale' => Locale::class,
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
     * Count the current members of each club in the list.
     *
     * The same set the member list's default selection shows, so the number on
     * the row of the club the viewer is working in doubles as a link to it. A
     * plain `withCount('members')` counted every row of `members`, the long
     * departed and the deceased included.
     *
     * @param  Builder<Club>  $query
     */
    #[Scope]
    protected function withCurrentMemberCount(Builder $query): void
    {
        $query->addSelect(['members_count' => AssignedMemberCount::ofClub()]);
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
     * @return non-empty-list<array{m: int, w: int, d: int}>
     */
    private static function getBlankStat(): array
    {
        return array_fill(0, 7, ['m' => 0, 'w' => 0, 'd' => 0]);
    }

    /**
     * Which of the seven rows of the age statistic a member belongs in.
     *
     * The boundaries live on `App\Enums\AgeBracket` so that this report, the
     * dashboard's age chart and the member selection behind that chart cannot
     * draw different lines. They are the association's — changing one changes
     * what the club submits.
     */
    private static function getStatIndex(int $age): int
    {
        return AgeBracket::of($age)->blsvRow();
    }

    /**
     * The members of every BLSV section, keyed by blsv_id, in the order the
     * files are written in.
     *
     * One query for all of them. The statistic used to run a
     * `Member::members()` query per section instead, so a club with seven
     * sections read and hydrated its 580 members eight times over — each of
     * those calls plucked every current member's id and every member id of the
     * section, then passed both back as IN lists. Measured on that club: 25
     * queries and 0.11s became 5 and 0.08s, with the files byte for byte the
     * same. The `inBlsvSections()` scope this replaced had no other caller.
     *
     * Driven by `$members` rather than by the rows: the surname/first-name
     * order the section files and the combined report are written in comes
     * from that one query, and the callers rely on it.
     *
     * @param  EloquentCollection<int, Member>  $members  the club's current members
     * @return array<int, list<Member>>
     */
    private static function membersByBlsvSection(EloquentCollection $members, CarbonInterface $keyDate): array
    {
        $rows = DB::table('member_section')
            ->join('sections', 'sections.id', '=', 'member_section.section_id')
            ->whereNotNull('sections.blsv_id')
            ->whereIn('member_section.member_id', $members->modelKeys())
            ->where('member_section.from', '<=', $keyDate)
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('member_section.to')->orWhere('member_section.to', '>=', $keyDate);
            })
            ->get(['member_section.member_id', 'sections.blsv_id']);

        $blsvIdsOf = [];

        foreach ($rows as $row) {
            // Keyed, not appended: two spells in the same section, or two
            // sections sharing a blsv_id, still make one member of it — the
            // same reading the IN list gave for free.
            $blsvIdsOf[$row->member_id][$row->blsv_id] = true;
        }

        $byBlsvId = [];

        foreach ($members as $member) {
            foreach (array_keys($blsvIdsOf[$member->id] ?? []) as $blsvId) {
                $byBlsvId[$blsvId][] = $member;
            }
        }

        return $byBlsvId;
    }

    /**
     * Builds the yearly BLSV age statistic, writes one CSV per section plus a
     * summary CSV and PDF to storage/downloads.
     *
     * Only ever meaningful for the club the caller is working in: Member and
     * Section carry ClubScope, so the numbers are the *current* club's however
     * this is invoked, while the files are named after `$this`. Calling it on
     * another Club would file one club's members under another's name — hence
     * ClubPolicy::blsvStatistic(), which refuses anything but the current club.
     *
     * @return array<int, array{name: string, href: string, description: string}>
     */
    public function getBLSVStatistic(): array
    {
        // Statistik ist zum 1. Januar, Austritte zum 31.12. und Eintritte zum 1.1. werden realisiert
        $keyDate = now()->startOfYear();
        Member::$_keyDate = $keyDate;
        $year = $keyDate->year;

        // storage/downloads is not in version control and is wiped by a
        // deploy, so it may simply not be there the first time a club builds
        // its statistic. The file_put_contents() calls below would fatal.
        File::ensureDirectoryExists(storage_path('downloads'));

        $sectionFiles = [];
        $usedFilenames = [];
        $stats = [-1 => self::getBlankStat()];
        $totals = self::getBlankStat();
        $totalRows = [];

        $members = Member::members()
            ->orderBy('surname')->orderBy('first_name')
            ->get();

        foreach ($members as $member) {
            $gender = $member->gender->blsvValue();
            $index = self::getStatIndex($member->age);
            $row = $totals[$index];
            $row[$gender]++;
            $totals[$index] = $row;
        }

        $stats[-1] = $totals;

        $membersByBlsvId = self::membersByBlsvSection($members, $keyDate);

        foreach (Section::whereNotNull('blsv_id')->orderBy('blsv_id')->get() as $section) {
            $rows = [];
            $stat = self::getBlankStat();

            foreach ($membersByBlsvId[$section->blsv_id] ?? [] as $member) {
                $gender = $member->gender->blsvValue();

                $rows[] = [
                    'surname' => $member->surname,
                    'first_name' => $member->first_name,
                    'gender' => $gender,
                    'birthday' => $member->birthday,
                    'blsv_id' => $section->blsv_id,
                ];

                $index = self::getStatIndex($member->age);
                $statRow = $stat[$index];
                $statRow[$gender]++;
                $stat[$index] = $statRow;
            }

            if ($rows !== []) {
                $stats[$section->blsv_id] = $stat + ['name' => $section->name];
                $totalRows = [...$totalRows, ...$rows];

                // Two names differing only in a path separator collapse to
                // the same file; the blsv_id keeps them apart rather than
                // letting one silently overwrite the other.
                $filename = self::pathSafe("BE{$year}_{$section->name}");
                if (isset($usedFilenames[$filename])) {
                    $filename .= "_{$section->blsv_id}";
                }
                $usedFilenames[$filename] = true;

                // No header on a section file, unlike the two that cover the
                // whole club — that is how lsverein7 wrote them.
                $sectionFiles[] = $this->writeDownload(
                    "{$filename}.csv",
                    BlsvMemberReport::csv($rows, withHeader: false),
                    __('Section: :name (CSV)', ['name' => $section->name]),
                    __('Only the members of this section'),
                );
            }
        }

        $pdf = new BlsvPdf;

        // The age statistic first, then the two files that cover every
        // section, then the sections themselves in BLSV order. The Excel file
        // sits directly behind the statistic because it is the one the club
        // actually submits.
        return [
            $this->writeDownload(
                'blsv_stat.pdf',
                $pdf->getOutput($stats, $keyDate, $this->name),
                __('Age statistic (PDF)'),
                __('Members by age and gender, per section and in total'),
            ),
            $this->writeDownload(
                "BE{$year}_Gesamt.xlsx",
                BlsvMemberReport::xlsx($totalRows),
                __('Member report (Excel)'),
                __('Every section in one file — this is what the club submits'),
            ),
            $this->writeDownload(
                "BE{$year}_Gesamt.csv",
                BlsvMemberReport::csv($totalRows),
                __('Member report (CSV)'),
                __('The same rows, should the association not take Excel'),
            ),
            ...$sectionFiles,
        ];
    }

    /**
     * A generated file's name, made safe to build a path with.
     *
     * Only what would actually break a path is replaced: the separators, the
     * control characters, and leading or trailing dots and spaces. Umlauts,
     * spaces and `&` stay — they are valid in a filename and route() encodes
     * them for the URL. A section called "Fitness&Turnen" therefore keeps the
     * file it has always been given.
     *
     * The escaping belongs here rather than in SectionValidationRules: a name
     * the club chose is a club matter, and a rule that forbids a slash to
     * protect a path forbids it everywhere else too. Until 2026-08-29 that
     * rule existed, and it locked the live "Fitness&Turnen" out of its own
     * edit form, because `&` was not in the allowed set either.
     *
     * Idempotent, so a caller that has already sanitised may pass through.
     */
    private static function pathSafe(string $name): string
    {
        $safe = str_replace(['/', '\\', DIRECTORY_SEPARATOR], '-', $name);

        // Byte class on purpose: no /u, so this cannot fail on a name that is
        // not valid UTF-8, and a multi-byte character has no low bytes to hit.
        $safe = preg_replace('/[\x00-\x1f\x7f]/', '', $safe) ?? $safe;
        $safe = trim($safe, ' .');

        return $safe === '' ? 'Abteilung' : $safe;
    }

    /**
     * Write one generated file to storage/downloads and describe it for the
     * download list. The name is stored with the club prefix but handed out
     * bare: DownloadController puts the *caller's* prefix back on, so a URL
     * can never name another club's file.
     *
     * @return array{name: string, href: string, description: string}
     */
    private function writeDownload(string $filename, string $contents, string $label, string $description): array
    {
        $filename = self::pathSafe($filename);

        file_put_contents(storage_path("downloads/{$this->id}_".$filename), $contents);

        // route(), not "/downloads/{$filename}": a section name may carry
        // spaces or umlauts (see the name rule in SectionValidationRules),
        // and only this encodes them.
        return [
            'name' => $label,
            'description' => $description,
            'href' => route('downloads.show', $filename, absolute: false),
        ];
    }

    public function calcBlsvDebit(float $childrenDue, float $teenDue, float $adultDue): float
    {
        $children = Member::members()->ageRange(null, 13)->count();
        $teens = Member::members()->ageRange(14, 17)->count();
        $adults = Member::members()->ageRange(18, null)->count();

        return ($children * $childrenDue) + ($teens * $teenDue) + ($adults * $adultDue);
    }
}

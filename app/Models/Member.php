<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Models\Scopes\ClubScope;
use Carbon\CarbonInterface;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $club_id
 * @property int $member_id
 * @property string $surname
 * @property string $first_name
 * @property Gender $gender
 * @property CarbonInterface $birthday
 * @property CarbonInterface|null $death_day
 * @property string $street
 * @property string $zipcode
 * @property string $city
 * @property string|null $email
 * @property string|null $phone
 * @property PaymentMethod $payment_method
 * @property string|null $bank
 * @property string|null $account_owner
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $memo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read int $age
 */
#[Fillable([
    'club_id',
    'member_id',
    'surname',
    'first_name',
    'gender',
    'birthday',
    'death_day',
    'street',
    'zipcode',
    'city',
    'email',
    'phone',
    'payment_method',
    'bank',
    'account_owner',
    'iban',
    'bic',
    'memo',
])]
#[ScopedBy([ClubScope::class])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /**
     * @var array<int, array{id: string, name: string}>
     */
    public const EXPORT_FORMATS = [
        ['id' => 'pdf', 'name' => 'PDF'],
        ['id' => 'vcf', 'name' => 'vCard'],
        ['id' => 'csv', 'name' => 'CSV'],
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['age'];

    /**
     * Key date every age and membership calculation is evaluated against.
     */
    public static ?CarbonInterface $_keyDate = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'death_day' => 'date',
            'gender' => Gender::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public static function getKeyDate(): CarbonInterface
    {
        if (static::$_keyDate === null) {
            static::$_keyDate = now()->endOfDay();
        }

        return static::$_keyDate->copy();
    }

    /**
     * @return Attribute<int, never>
     */
    protected function age(): Attribute
    {
        return new Attribute(
            get: function (): int {
                $keyDate = $this->gone() ? $this->death_day : self::getKeyDate();

                return (int) $this->birthday->diffInYears($keyDate);
            },
        );
    }

    public function entry(): ?CarbonInterface
    {
        $entry = null;

        foreach ($this->memberships as $membership) {
            $entry = $entry === null ? $membership->pivot->from : $membership->pivot->from->min($entry);
        }

        return $entry;
    }

    public function honorThisYear(): int
    {
        $years = $this->membershipYears();

        return in_array($years, explode(',', (string) currentClub()->honor_years)) ? $years : 0;
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsToMany<Club, $this, ClubMember>
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->withPivot(['id', 'from', 'to', 'memo'])
            ->withTimestamps()
            ->using(ClubMember::class);
    }

    /**
     * @return BelongsToMany<Event, $this, EventMember>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)
            ->withPivot(['id', 'date', 'memo'])
            ->withTimestamps()
            ->orderBy('pivot_date', 'desc')
            ->using(EventMember::class);
    }

    /**
     * @return BelongsToMany<Item, $this, ItemMember>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)
            ->withPivot(['id', 'memo', 'from', 'to'])
            ->withTimestamps()
            ->orderBy('pivot_from', 'desc')
            ->using(ItemMember::class);
    }

    /**
     * @return BelongsToMany<Role, $this, MemberRole>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['id', 'from', 'to', 'memo'])
            ->withTimestamps()
            ->using(MemberRole::class);
    }

    /**
     * @return BelongsToMany<Section, $this, MemberSection>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class)
            ->withPivot(['id', 'from', 'to', 'memo'])
            ->withTimestamps()
            ->using(MemberSection::class);
    }

    /**
     * @return BelongsToMany<Subscription, $this, MemberSubscription>
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class)
            ->withPivot(['id', 'memo'])
            ->withTimestamps()
            ->using(MemberSubscription::class);
    }

    public function born(): bool
    {
        return inRange($this->birthday, null, self::getKeyDate());
    }

    public function gone(): bool
    {
        return inRange($this->death_day, null, self::getKeyDate());
    }

    public function alive(): bool
    {
        return $this->born() && ! $this->gone();
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->surname;
    }

    public function accountNumber(): string
    {
        return ltrim(str_replace(' ', '', substr($this->iban, -12)), '0');
    }

    public function isMember(): bool
    {
        if (! $this->alive()) {
            return false;
        }

        $keyDate = self::getKeyDate();

        foreach ($this->memberships as $membership) {
            if (inRange($keyDate, $membership->pivot->from, $membership->pivot->to)) {
                return true;
            }
        }

        return false;
    }

    public function membershipYears(): int
    {
        $keyDate = self::getKeyDate()->min($this->death_day);
        $years = 0;

        foreach ($this->memberships as $membership) {
            $pivot = $membership->pivot;
            if ($pivot->from >= $keyDate) {
                return 0;
            }

            $to = $keyDate->min($pivot->to);

            // roughly calculation
            $years += $to->year - $pivot->from->year;
        }

        return $years;
    }

    public function lastEvent(): ?string
    {
        return $this->events->first()?->name;
    }

    public function currentSections(): string
    {
        $keyDate = self::getKeyDate();
        $sections = [];

        foreach ($this->sections as $section) {
            if (inRange($keyDate, $section->pivot->from, $section->pivot->to)) {
                $sections[] = $section->name;
            }
        }

        return implode('|', $sections);
    }

    public function currentRoles(): string
    {
        $keyDate = self::getKeyDate();
        $roles = [];

        foreach ($this->roles as $role) {
            if (inRange($keyDate, $role->pivot->from, $role->pivot->to)) {
                $roles[] = $role->name;
            }
        }

        return implode('|', $roles);
    }

    public function currentSubscriptions(): string
    {
        return $this->subscriptions->pluck('name')->join('|');
    }

    /**
     * @return Collection<int, int>
     */
    public static function memberIds(?CarbonInterface $keyDate = null): Collection
    {
        if ($keyDate === null) {
            $keyDate = self::getKeyDate();
        }

        return DB::table('members')->join('club_member', 'members.id', '=', 'club_member.member_id')
            ->where('members.club_id', currentClubId())
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('members.death_day')->orWhere('members.death_day', '>', $keyDate);
            })
            ->where('club_member.from', '<=', $keyDate)
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('club_member.to')->orWhere('club_member.to', '>=', $keyDate);
            })
            ->pluck('members.id');
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function members(Builder $query, ?CarbonInterface $keyDate = null): void
    {
        $query->whereIn('id', self::memberIds($keyDate));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function noMembers(Builder $query, ?CarbonInterface $keyDate = null): void
    {
        $query->whereNotIn('id', self::memberIds($keyDate));
    }

    /**
     * @param  Builder<Member>  $query
     * @param  list<int>|int  $sections
     */
    #[Scope]
    protected function inSections(Builder $query, array|int $sections, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', DB::table('members')
            ->join('member_section', 'members.id', '=', 'member_section.member_id')
            ->where('members.club_id', currentClubId())
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('members.death_day')->orWhere('members.death_day', '>', $keyDate);
            })
            ->whereIn('member_section.section_id', Arr::wrap($sections))
            ->where('member_section.from', '<=', $keyDate)
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('member_section.to')->orWhere('member_section.to', '>=', $keyDate);
            })
            ->pluck('members.id')
        );
    }

    /**
     * @param  Builder<Member>  $query
     * @param  list<int>|int  $sections
     */
    #[Scope]
    protected function inBlsvSections(Builder $query, array|int $sections, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', DB::table('members')
            ->join('member_section', 'members.id', '=', 'member_section.member_id')
            ->join('sections', 'sections.id', '=', 'member_section.section_id')
            ->where('members.club_id', currentClubId())
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('members.death_day')->orWhere('members.death_day', '>', $keyDate);
            })
            ->whereIn('sections.blsv_id', Arr::wrap($sections))
            ->where('member_section.from', '<=', $keyDate)
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('member_section.to')->orWhere('member_section.to', '>=', $keyDate);
            })
            ->pluck('members.id')
        );
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function ageRange(Builder $query, ?int $from, ?int $to): void
    {
        if ($to !== null) {
            // geboren morgen vor ($to + 1) Jahren ab 00:00
            $query->where('birthday', '>=', self::getKeyDate()->addDay()->subYears($to + 1)->startOfDay());
        }
        if ($from !== null) {
            // geboren vor $from Jahren bis 23:59
            $query->where('birthday', '<=', self::getKeyDate()->subYears($from)->endOfDay());
        }
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function milestoneBirthdays(Builder $query, ?int $year = null): void
    {
        $query->whereRaw('? - YEAR(birthday) in (50,60,70,80,90,100)', [$year ?? self::getKeyDate()->year]);
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function joined(Builder $query, ?int $year = null): void
    {
        $query->whereIn('id', ClubMember::whereRaw('YEAR(`from`) = ?', [$year ?? self::getKeyDate()->year])
            ->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function retired(Builder $query, ?int $year = null): void
    {
        $query->whereIn('id', ClubMember::whereRaw('YEAR(`to`) = ?', [$year ?? self::getKeyDate()->year])
            ->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function dead(Builder $query, ?int $year = null): void
    {
        $query->whereRaw('(YEAR(`death_day`) = ?)', [$year ?? self::getKeyDate()->year]);
    }

    /**
     * @param  Builder<Member>  $query
     * @param  list<PaymentMethod>|PaymentMethod  $methods
     */
    #[Scope]
    protected function paymentMethods(Builder $query, array|PaymentMethod $methods): void
    {
        $query->whereIn('payment_method', array_map(
            fn (PaymentMethod $method): string => $method->value,
            Arr::wrap($methods)
        ));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function hasAccount(Builder $query): void
    {
        $query->where('iban', '<>', '');  // schliesst wohl auch NULL aus
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function hadEvent(Builder $query, ?int $id = null, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', EventMember::where('date', '<', $keyDate)
            ->when($id, function ($query, $id) {
                $query->where('event_id', $id);
            })->pluck('member_id')
        );
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function hasRole(Builder $query, ?int $id = null, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', MemberRole::where('from', '<', $keyDate)
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('to')->orWhere('to', '>', $keyDate);
            })
            ->when($id, function ($query, $id) {
                $query->where('role_id', $id);
            })
            ->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function everRole(Builder $query, ?int $id = null, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', MemberRole::where('from', '<', $keyDate)
            ->when($id, function ($query, $id) {
                $query->where('role_id', $id);
            })->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function hasItem(Builder $query, ?int $id = null, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', ItemMember::where('from', '<', $keyDate)
            ->where(function ($query) use ($keyDate) {
                $query->whereNull('to')->orWhere('to', '>', $keyDate);
            })
            ->when($id, function ($query, $id) {
                $query->where('item_id', $id);
            })
            ->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function everItem(Builder $query, ?int $id = null, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $query->whereIn('id', ItemMember::where('from', '<', $keyDate)
            ->when($id, function ($query, $id) {
                $query->where('item_id', $id);
            })->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     * @param  list<int>|int|null  $subscriptionTypes
     */
    #[Scope]
    protected function hasSubscription(Builder $query, array|int|null $subscriptionTypes = null): void
    {
        $query->whereIn('id', MemberSubscription::when($subscriptionTypes,
            function ($query, $subscriptionTypes) {
                $query->whereIn('subscription_id', Arr::wrap($subscriptionTypes));
            })->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     * @param  list<int>|int|null  $subscriptionTypes
     */
    #[Scope]
    protected function noSubscription(Builder $query, array|int|null $subscriptionTypes = null): void
    {
        $query->whereNotIn('id', MemberSubscription::when($subscriptionTypes,
            function ($query, $subscriptionTypes) {
                $query->whereIn('subscription_id', Arr::wrap($subscriptionTypes));
            })->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function dueHonor(Builder $query, ?CarbonInterface $keyDate = null): void
    {
        $keyDate ??= self::getKeyDate();

        $honorYears = collect(explode(',', (string) currentClub()->honor_years))
            ->map(fn (string $year): int => (int) trim($year))
            ->filter()
            ->values();

        if ($honorYears->isEmpty()) {
            return;
        }

        $query->whereIn('id',
            ClubMember::groupBy('member_id') // add if member has several memberships
                ->havingRaw(
                    'FIND_IN_SET(SUM(YEAR(LEAST(IFNULL(`to`, ?), ?)) - YEAR(`from`)), ?)',
                    [$keyDate, $keyDate, $honorYears->implode(',')]
                )->pluck('member_id'));
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function like(Builder $query, string $like): void
    {
        $like = '%'.$like.'%';
        $query->where(function ($query) use ($like) {
            $query->where('first_name', 'like', $like)
                ->orWhere('surname', 'like', $like)
                ->orWhere('street', 'like', $like)
                ->orWhere('zipcode', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('memo', 'like', $like);
        });
    }
}

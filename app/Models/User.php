<?php

namespace App\Models;

use App\Enums\ActionType;
use App\Enums\ClubRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property bool $admin
 * @property string|null $profile_image
 * @property string $locale
 * @property int|null $club_id
 * @property int|null $created_by
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'admin',
    'profile_image',
    'locale',
    'club_id',
    'created_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Cache of the resolved club role, keyed by club id.
     *
     * @var array<int, int>
     */
    protected array $clubRoles = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'admin' => 'boolean',
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
     * @return BelongsToMany<Club, $this, ClubUser>
     */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->withPivot(['role'])
            ->withTimestamps()
            ->using(ClubUser::class);
    }

    /**
     * @return HasMany<Tracing, $this>
     */
    public function tracings(): HasMany
    {
        return $this->hasMany(Tracing::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeHasClub(Builder $query, ?int $clubId = null): void
    {
        $query->whereRaw(
            'users.id in (select user_id from club_user where club_id = ?)',
            [$clubId ?? currentClubId()]
        );
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithLastLoginAt(Builder $query): void
    {
        $query->addSelect([
            'last_login_at' => Tracing::select('at')
                ->whereColumn('tracings.user_id', 'users.id')
                ->where('action_type', ActionType::Login)
                ->orderByDesc('at')
                ->take(1),
        ])->withCasts(['last_login_at' => 'datetime']);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithRole(Builder $query, ?int $clubId = null): void
    {
        $query->addSelect([
            'role' => ClubUser::select('role')
                ->where('club_id', $clubId ?? currentClubId())
                ->whereColumn('user_id', 'users.id')
                ->take(1),
        ]);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeOrderByLastLogin(Builder $query): void
    {
        $query->orderByDesc(Tracing::select('at')
            ->whereColumn('user_id', 'users.id')
            ->where('action_type', ActionType::Login)
            ->latest('at')
            ->take(1)
        );
    }

    public function hasAdminRights(?int $clubId = null): bool
    {
        return $this->clubRole($clubId) >= ClubRole::Admin->value;
    }

    public function hasAdvancedRights(?int $clubId = null): bool
    {
        return $this->clubRole($clubId) >= ClubRole::Advanced->value;
    }

    public function hasClubRole(ClubRole $clubRole, ?int $clubId = null): bool
    {
        return $this->clubRole($clubId) === $clubRole->value;
    }

    public function clubRole(?int $clubId = null): int
    {
        $clubId = (int) ($clubId ?? currentClubId());

        if (! isset($this->clubRoles[$clubId])) {
            $role = ClubUser::where('club_id', $clubId)
                ->where('user_id', $this->id)
                ->value('role');

            $this->clubRoles[$clubId] = $role ?? -1;
        }

        return $this->clubRoles[$clubId];
    }

    public function lastLogin(): ?CarbonInterface
    {
        return $this->tracings()->actionType(ActionType::Login)->orderByDesc('at')->first()?->at;
    }

    public function profileURL(): string
    {
        if ($this->profile_image) {
            if (file_exists(public_path('storage/profile/'.$this->profile_image))) {
                return asset('storage/profile/'.$this->profile_image);
            }

            $this->update(['profile_image' => null]);
        }

        return 'https://www.gravatar.com/avatar/'.
            md5(strtolower(trim($this->email))).
            '?d=mp&s=40';
    }

    public static function profilePath(string $stub = ''): string
    {
        return storage_path('app/public/profile').
            DIRECTORY_SEPARATOR.
            trim($stub, DIRECTORY_SEPARATOR);
    }

    public static function removeOrphanProfileImages(): int
    {
        $count = 0;

        foreach (glob(self::profilePath('*')) ?: [] as $filename) {
            if (User::where('profile_image', basename($filename))->first() === null) {
                unlink($filename);
                $count++;
            }
        }

        return $count;
    }

    public function switchClub(int $clubId): bool
    {
        if (! $this->clubs()->where('club_id', $clubId)->exists()) {
            return false;
        }

        $this->update(['club_id' => $clubId]);

        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function availableRoles(): array
    {
        return [
            ClubRole::Basic->value => 'Basic',
            ClubRole::Advanced->value => 'Advanced',
            ClubRole::Admin->value => 'Admin',
        ];
    }
}

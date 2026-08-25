<?php

namespace App\Models;

use App\Enums\ActionType;
use App\Enums\ClubRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property bool $admin
 * @property string|null $profile_image
 * @property-read string $avatar
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
#[Appends(['avatar'])]
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
    #[Scope]
    protected function hasClub(Builder $query, ?int $clubId = null): void
    {
        $query->whereRaw(
            'users.id in (select user_id from club_user where club_id = ?)',
            [$clubId ?? currentClubId()]
        );
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function withLastLoginAt(Builder $query): void
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
    #[Scope]
    protected function withRole(Builder $query, ?int $clubId = null): void
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
    #[Scope]
    protected function orderByLastLogin(Builder $query): void
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

    /**
     * The URL of the user's profile picture, appended to every serialization
     * so the frontend's UserInfo avatar always has a src to fall back on.
     *
     * @return Attribute<string, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->profileURL());
    }

    public function profileURL(): string
    {
        if ($this->profile_image) {
            if (self::profileDisk()->exists(self::profileStoragePath($this->profile_image))) {
                return self::profileDisk()->url(self::profileStoragePath($this->profile_image));
            }

            $this->update(['profile_image' => null]);
        }

        return 'https://www.gravatar.com/avatar/'.
            md5(strtolower(trim($this->email))).
            '?d=mp&s=40';
    }

    /**
     * The "public" disk, on which profile images are stored - resolved
     * lazily (not cached in a static) so tests can swap in Storage::fake().
     */
    public static function profileDisk(): Filesystem
    {
        return Storage::disk('public');
    }

    public static function profileStoragePath(string $filename): string
    {
        return 'profile/'.trim($filename, '/');
    }

    public static function removeOrphanProfileImages(): int
    {
        $count = 0;
        $disk = self::profileDisk();

        foreach ($disk->files('profile') as $path) {
            // Skip dotfiles: storage/app/public/profile/.gitignore is what keeps
            // the directory in the repository, and nothing references it, so
            // an unfiltered sweep deletes it on the first run.
            if (str_starts_with(basename($path), '.')) {
                continue;
            }

            if (static::where('profile_image', basename($path))->first() === null) {
                $disk->delete($path);
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
     * The selectable club roles, as {id, name} options for the frontend.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function availableRoles(): array
    {
        return array_map(
            fn (ClubRole $role): array => ['id' => $role->value, 'name' => $role->label()],
            ClubRole::cases()
        );
    }

    /**
     * The languages a user account can be set to.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function availableLocales(): array
    {
        return [
            ['id' => 'de', 'name' => __('German')],
            ['id' => 'en', 'name' => __('English')],
        ];
    }
}

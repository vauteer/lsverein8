<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('users/Index', [
            'users' => UserResource::collection(
                User::query()
                    ->hasClub()
                    ->withRole()
                    ->withLastLoginAt()
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where(fn (Builder $inner) => $inner
                            ->where('users.name', 'like', "%{$search}%")
                            ->orWhere('users.email', 'like', "%{$search}%")))
                    ->orderBy('users.name')
                    ->orderBy('users.id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/Create', $this->formOptions());
    }

    /**
     * Create a user account for the current club. An email that already has an
     * account anywhere in the installation is added to this club instead, so
     * the person keeps a single login across the clubs they belong to.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->existingUser();

        if ($user === null) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'],
                // Never a usable password: the account is claimed through the
                // password-reset link sent below, so no plaintext secret is
                // ever mailed out or written to the log.
                'password' => Str::password(40),
                'club_id' => currentClubId(),
                'created_by' => $request->user()->id,
            ]);

            Password::sendResetLink(['email' => $user->email]);

            $message = __('User created. They have been emailed a link to set their password.');
        } else {
            $message = __('This email already belongs to an existing account, so it was added to this club. Name, phone, and language were not changed.');
        }

        $user->clubs()->attach(currentClubId(), ['role' => $data['role']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('users.index', ['page' => $this->pageOf($user)]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->scopedUser($user);

        return Inertia::render('users/Edit', [
            ...$this->formOptions(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale?->value,
                'role' => $user->clubRole(),
            ],
            'deletable' => $request->user()->can('delete', $user),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->scopedUser($user);

        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'locale' => $data['locale'],
        ]);

        $user->clubs()->updateExistingPivot(currentClubId(), ['role' => $data['role']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index', ['page' => $this->pageOf($user)]);
    }

    /**
     * Remove the user from the current club. If that was the only club they
     * belonged to, the account itself is deleted rather than leaving an
     * orphaned login with no club to access.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->scopedUser($user);

        $page = $this->pageOf($user);

        $user->clubs()->detach(currentClubId());

        $remainingClubId = $user->clubs()->orderBy('clubs.id')->value('clubs.id');

        if ($remainingClubId === null) {
            $user->delete();
        } elseif ($user->club_id === currentClubId()) {
            // Their active club is the one they just lost access to.
            $user->update(['club_id' => $remainingClubId]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * @return array{roles: list<array{id: int, name: string}>, locales: list<array{id: string, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'roles' => User::availableRoles(),
            'locales' => Locale::options(),
        ];
    }

    /**
     * Ensure the given user actually belongs to the current club, so a club
     * admin cannot reach another club's account by guessing its id.
     */
    private function scopedUser(User $user): void
    {
        abort_if(! $user->clubs()->whereKey(currentClubId())->exists(), 404);
    }

    /**
     * The index page on which the given user appears.
     */
    private function pageOf(User $user): int
    {
        $position = User::query()
            ->hasClub()
            ->where(fn (Builder $query) => $query
                ->where('users.name', '<', $user->name)
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('users.name', $user->name)
                    ->where('users.id', '<=', $user->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the user index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(User::query()->hasClub()->count() / self::PER_PAGE));
    }
}

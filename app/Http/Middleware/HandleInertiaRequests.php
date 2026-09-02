<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Models\Debit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        // Not currentClub(): that helper dereferences auth()->user() and would
        // fatal for guests, who still render the login page through here.
        $currentClub = $request->user()?->club_id === null
            ? null
            : Club::find($request->user()->club_id);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => App::getLocale(),
            'auth' => [
                'user' => $request->user(),
                'canManageUsers' => (bool) $request->user()?->hasAdminRights(),
                // Resolved through the gate rather than reading users.admin
                // again, so the sidebar entry and the route cannot disagree.
                'canViewLogs' => (bool) $request->user()?->can('viewLogViewer'),
                'canViewTelescope' => (bool) $request->user()?->can('viewTelescope'),
                'canManageBackups' => (bool) $request->user()?->can('manageBackups'),
                // Admin-only screen, so the sidebar entry is resolved
                // through the policy rather than repeating its check.
                'canManageDebits' => (bool) $request->user()?->can('viewAny', Debit::class),
                // Root only: the list of every club in the installation.
                'canManageClubs' => (bool) $request->user()?->can('viewAny', Club::class),
                // A club admin has no list but may edit the club they are in,
                // which is what puts a single "Verein" entry in their sidebar.
                'canEditCurrentClub' => $currentClub !== null
                    && $request->user()->can('update', $currentClub),
                // The BLSV entry appears only for a club that reports to the
                // association, and only for an admin of it — the same policy
                // the two routes carry, so entry and route cannot disagree.
                'canReportToBlsv' => $currentClub !== null
                    && $request->user()->can('blsvStatistic', $currentClub),
                // Set while a root account is logged in as somebody else, so
                // the banner can name them and offer a way back.
                'impersonator' => $impersonatorId === null
                    ? null
                    : User::find((int) $impersonatorId)?->only(['id', 'name']),
            ],
            'currentClub' => $currentClub === null ? null : [
                'id' => $currentClub->id,
                'name' => $currentClub->name,
                'logo_url' => $currentClub->logoURL(),
                // Resolved server-side rather than shipping the raw enum: a
                // wordmark logo already carries the name, so repeating it
                // beside the image is what this setting exists to prevent.
                'show_logo' => $currentClub->identity_display->showsLogo(),
                'show_name' => $currentClub->identity_display->showsName(),
                // The inventory is opt-in per club, so the sidebar entry only
                // appears where ItemPolicy would let the screens open.
                'uses_items' => $currentClub->use_items,
            ],
            // The clubs this user may switch between, for the sidebar picker.
            // Only their own memberships: a root account switches to a club it
            // does not belong to from the club list instead, which would
            // otherwise make this a dropdown of the whole installation.
            'switchableClubs' => $this->switchableClubs($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The user's own clubs, or an empty list when there is nothing to choose
     * between - the picker is not rendered for a single club.
     *
     * @return list<array{id: int, name: string, current: bool}>
     */
    private function switchableClubs(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $clubs = $user->clubs()->orderBy('name')->get(['clubs.id', 'clubs.name']);

        if ($clubs->count() < 2) {
            return [];
        }

        // array_values, not Collection::values(): only the native call proves
        // a list to phpstan, which the declared return type needs.
        return array_values($clubs
            ->map(fn (Club $club): array => [
                'id' => $club->id,
                'name' => $club->name,
                'current' => $club->id === $user->club_id,
            ])
            ->all());
    }
}

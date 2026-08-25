<?php

namespace App\Http\Middleware;

use App\Models\Club;
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
                // Set while a root account is logged in as somebody else, so
                // the banner can name them and offer a way back.
                'impersonator' => $impersonatorId === null
                    ? null
                    : User::find((int) $impersonatorId)?->only(['id', 'name']),
            ],
            'currentClub' => $currentClub === null ? null : [
                'name' => $currentClub->name,
                'logo_url' => $currentClub->logoURL(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    /**
     * Log the acting root user in as the given user, remembering the root
     * user's own id so the session can be restored later.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $rootUser = $request->user();

        abort_if($rootUser->cannot('impersonate', $user), 403);

        $request->session()->put('impersonator_id', $rootUser->id);
        Auth::login($user);
        $request->session()->regenerate();

        // A lingering "remember me" cookie for the root account lets the
        // session guard silently fall back to it if the session's auth key is
        // ever read before this write is visible, reverting the swap.
        Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logged in as :name.', ['name' => $user->name])]);

        return to_route('dashboard');
    }

    /**
     * Return to the root account that started the impersonation. Reachable by
     * the impersonated (non-root) session, not just root users.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        $rootUser = $impersonatorId === null ? null : User::find((int) $impersonatorId);

        abort_if($rootUser === null || ! $rootUser->admin, 403);

        $request->session()->forget('impersonator_id');
        Auth::login($rootUser);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Returned to your account.')]);

        return to_route('users.index');
    }
}

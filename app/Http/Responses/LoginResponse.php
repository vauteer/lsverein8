<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Where a sign-in ends up: the screen the user chose in their settings.
 *
 * Replaces Fortify's own response, which redirects to the fixed
 * `config('fortify.home')`. That config value stays as it is — it is what
 * Fortify falls back on elsewhere — but it is no longer what a login obeys.
 *
 * `intended()` still wins, and deliberately so: somebody who followed a link
 * to a member and was asked to sign in first wants that member, not their
 * usual starting screen. This is why `/` (HomeController) redirects a guest
 * straight to the login instead of bouncing them through a protected page —
 * that would leave an intended URL behind and silently beat the preference.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var Request $request */
        return redirect()->intended($request->user()->landingPage()->url());
    }
}

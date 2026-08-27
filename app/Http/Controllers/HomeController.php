<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The root only points the way: the app has no public page.
 *
 * A guest goes straight to the login, **not** through a protected screen. The
 * detour would work — the `auth` middleware would bounce them — but it leaves
 * `/dashboard` behind as the intended URL, and `LoginResponse` honours an
 * intended URL over the user's chosen landing page. The preference would then
 * be silently overridden for anybody who starts at the root, which is most
 * people.
 *
 * A signed-in user goes to that chosen page.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return $user === null
            ? to_route('login')
            : redirect($user->landingPage()->url());
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the language in force: the user's own choice if they made one,
     * otherwise their club's, and the configured application locale for
     * guests or a user without a club.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Plain -> after the nullsafe call: ?? suppresses the property fetch
        // when effectiveLocale() returns null, and phpstan rejects the second
        // nullsafe as dead.
        App::setLocale(
            $request->user()?->effectiveLocale()->value ?? config('app.locale')
        );

        return $next($request);
    }
}

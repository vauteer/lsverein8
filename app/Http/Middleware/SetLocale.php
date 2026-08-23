<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the authenticated user's own language, falling back to the
     * configured application locale for guests.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($request->user()?->locale ?: config('app.locale'));

        return $next($request);
    }
}

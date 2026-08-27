<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Who made the application, and what it is built with.
 *
 * Open to every signed-in account, not just an admin: the credits and the
 * contact address say nothing about a club, and the address is the one a user
 * needs when something is wrong.
 *
 * The credits name the versions the application is built for, not the patch
 * release it happens to run on, so both are cut back: Laravel to its major
 * version, PHP to major and minor.
 */
class AboutController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('About', [
            'appName' => config('app.name'),
            'laravelVersion' => explode('.', app()->version())[0],
            'phpVersion' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        ]);
    }
}

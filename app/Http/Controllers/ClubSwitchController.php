<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Switching the club a user is working in.
 *
 * `currentClubId()` reads `users.club_id`, and every ClubScope/
 * ClubWithSharedScope model keys off it, so this one column decides which
 * club's members, sections, honours and roles the whole app shows. The switch
 * is therefore a write to the user's own row, not session state: it survives
 * logout and matches what lsverein7 did.
 */
class ClubSwitchController extends Controller
{
    public function store(Request $request, Club $club): RedirectResponse
    {
        $request->user()->update(['club_id' => $club->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Switched to :name.', ['name' => $club->name]),
        ]);

        // Back to the dashboard rather than the previous page: that page was
        // rendered for the old club and may not even exist in the new one.
        return to_route('dashboard');
    }
}

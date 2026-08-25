<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'status' => $request->session()->get('status'),
            // Drives the "Remove photo" button: the avatar always has a src
            // (Gravatar is the fallback), so its presence proves nothing.
            'hasProfileImage' => $request->user()->profile_image !== null,
            'locale' => $request->user()->locale?->value,
            'locales' => Locale::options(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email', 'locale']));

        if ($request->boolean('remove_profile_image')) {
            $user->profile_image = null;
        } elseif ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = $file->hashName();
            User::profileDisk()->putFileAs('profile', $file, $filename);
            $user->profile_image = $filename;
        }

        $user->save();

        // Sweeps the file left behind by a replaced or removed photo.
        User::removeOrphanProfileImages();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

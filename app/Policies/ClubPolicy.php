<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    /**
     * The club list is the whole installation, so only a root account sees it.
     * A club admin has no list — they reach their own club's form directly.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->admin;
    }

    public function create(User $user): bool
    {
        return (bool) $user->admin;
    }

    /**
     * A root account may edit any club. A club admin may edit only the club
     * they are currently working in: switching first is what makes another of
     * their clubs editable, which keeps "which club am I changing?" answerable
     * from the sidebar alone.
     */
    public function update(User $user, Club $club): bool
    {
        if ($user->admin) {
            return true;
        }

        return $club->id === currentClubId() && $user->hasAdminRights($club->id);
    }

    /**
     * Root-only, and only for a club nothing hangs off any more — the foreign
     * keys cascade, so deleting a populated club would erase its members and
     * their whole history. The current club is never deletable: it would pull
     * the ground out from under the acting session.
     */
    public function delete(User $user, Club $club): bool
    {
        return $user->admin
            && $club->id !== currentClubId()
            && ! $club->isUsed();
    }

    /**
     * Switching the club the user is working in. A root account may switch to
     * any club — that is how it inspects one it does not belong to. Everybody
     * else only to a club they are a member of.
     */
    /**
     * The club's own slice of the database, as SQL.
     *
     * Same gate as update(), which is exactly right: root for any club, a club
     * admin only for the one they are working in. The route carries the club,
     * so root exports the club they are looking at rather than the one they
     * happen to be switched into.
     */
    public function export(User $user, Club $club): bool
    {
        return $this->update($user, $club);
    }

    public function switchTo(User $user, Club $club): bool
    {
        if ($club->id === $user->club_id) {
            return false;
        }

        return (bool) $user->admin || $user->clubs()->whereKey($club->id)->exists();
    }
}

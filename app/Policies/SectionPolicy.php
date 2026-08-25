<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    /**
     * Everybody in the club may look at its sections; only a club admin may
     * change them.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAdminRights();
    }

    /**
     * Sections shared across all clubs (club_id null) belong to the
     * installation, not to a club, so only a root account may change them.
     */
    public function update(User $user, Section $section): bool
    {
        if ($section->club_id === null) {
            // (bool): `admin` is nullable in the database, so the boolean cast
            // still yields null for accounts that never had the flag set.
            return (bool) $user->admin;
        }

        return $user->hasAdminRights($section->club_id);
    }

    /**
     * A section that any member has ever been assigned to is kept, so the
     * member's history does not lose the name it refers to.
     */
    public function delete(User $user, Section $section): bool
    {
        return $this->update($user, $section) && ! $section->isUsed();
    }
}

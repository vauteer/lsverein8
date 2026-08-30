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
     * A section belongs to one club, so its club's admins may change it.
     *
     * There is no installation-wide section left for a root account to own:
     * until 2026-08-30 a null `club_id` made a row everyone's, and only
     * `users.admin` could edit it. Root now reaches a section the same way it
     * reaches everything else in a club, by switching into it.
     */
    public function update(User $user, Section $section): bool
    {
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

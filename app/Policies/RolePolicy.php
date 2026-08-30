<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Everybody in the club may look at its roles; only a club admin may
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
     * A role belongs to one club, so its club's admins may change it.
     *
     * There is no installation-wide role left for a root account to own:
     * until 2026-08-30 a null `club_id` made a row everyone's, and only
     * `users.admin` could edit it. A new club gets its own copies of
     * Role::DEFAULTS instead.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasAdminRights($role->club_id);
    }

    /**
     * A role any member has ever held is kept, so the member's history does
     * not lose the name it refers to. The member_role foreign key is
     * ON DELETE RESTRICT, so the database would refuse it anyway.
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role) && ! $role->isUsed();
    }
}

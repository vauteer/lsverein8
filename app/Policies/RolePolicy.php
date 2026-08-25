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
     * Roles shared across all clubs (club_id null) belong to the
     * installation, not to a club, so only a root account may change them.
     */
    public function update(User $user, Role $role): bool
    {
        if ($role->club_id === null) {
            // (bool): `users.admin` is `NOT NULL DEFAULT 0`, but a model that
            // was created without an explicit `admin` never loads that default
            // back, so the attribute is absent and `$user->admin` is null
            // until the row is re-read. Casting keeps the bool return honest.
            return (bool) $user->admin;
        }

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

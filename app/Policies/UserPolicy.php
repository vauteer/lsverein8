<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Managing users of the current club requires club admin rights.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminRights();
    }

    public function create(User $user): bool
    {
        return $user->hasAdminRights();
    }

    /**
     * A root account (`users.admin`) may only be changed by itself, so a club
     * admin cannot edit, lock out, or take over the global superuser.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->admin && $user->id !== $model->id) {
            return false;
        }

        return $user->hasAdminRights();
    }

    public function delete(User $user, User $model): bool
    {
        // Deleting yourself from the user list would drop you out of the club
        // you are currently administering; settings/profile handles that case.
        if ($user->id === $model->id) {
            return false;
        }

        return $this->update($user, $model);
    }

    /**
     * Only a root account may log in as somebody else, and never as another
     * root account or as itself.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->admin && ! $model->admin && $user->id !== $model->id;
    }

    /**
     * Reserved for the global superuser (log viewer, cross-club tooling).
     */
    public function root(User $user): bool
    {
        return $user->admin;
    }
}

<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Everybody in the club may look at its events; only a club admin may
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
     * Events shared across all clubs (club_id null) belong to the
     * installation, not to a club, so only a root account may change them.
     */
    public function update(User $user, Event $event): bool
    {
        if ($event->club_id === null) {
            // (bool): `users.admin` is `NOT NULL DEFAULT 0`, but a model that
            // was created without an explicit `admin` never loads that default
            // back, so the attribute is absent and `$user->admin` is null
            // until the row is re-read. Casting keeps the bool return honest.
            return (bool) $user->admin;
        }

        return $user->hasAdminRights($event->club_id);
    }

    /**
     * An event that any member has ever been given is kept, so the member's
     * history does not lose the name it refers to. The event_member foreign
     * key is ON DELETE RESTRICT, so the database would refuse it anyway.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event) && ! $event->isUsed();
    }
}

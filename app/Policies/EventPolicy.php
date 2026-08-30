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
     * A event belongs to one club, so its club's admins may change it.
     *
     * There is no installation-wide event left for a root account to own:
     * until 2026-08-30 a null `club_id` made a row everyone's, and only
     * `users.admin` could edit it. A new club gets its own copies of
     * Event::DEFAULTS instead.
     */
    public function update(User $user, Event $event): bool
    {
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

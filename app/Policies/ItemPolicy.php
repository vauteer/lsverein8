<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    /**
     * The inventory is an opt-in feature: a club that has not switched on
     * `clubs.use_items` has no inventory at all, so the screens are refused
     * rather than merely hidden from the sidebar. lsverein7 only hid the nav
     * entry, which left /items reachable by typing the address.
     *
     * Root is not exempt: a root account always works inside one club (the
     * scopes hang off users.club_id), so they see the inventory of a club
     * that uses one, and switch clubs to reach another.
     */
    public function viewAny(User $user): bool
    {
        return currentClub()->use_items;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->hasAdminRights();
    }

    /**
     * No root-only branch, unlike sections, events and roles: `items.club_id`
     * is NOT NULL (verified against the live database), so an item always
     * belongs to a club and installation-wide rows cannot exist. That is also
     * why Item carries ClubScope rather than ClubWithSharedScope.
     */
    public function update(User $user, Item $item): bool
    {
        return $this->viewAny($user) && $user->hasAdminRights($item->club_id);
    }

    /**
     * An item that has ever been issued to a member is kept, so the member's
     * history does not lose the name it refers to. The item_member foreign key
     * is ON DELETE RESTRICT, so the database would refuse it anyway.
     */
    public function delete(User $user, Item $item): bool
    {
        return $this->update($user, $item) && ! $item->isUsed();
    }
}

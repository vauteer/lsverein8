<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    /**
     * Everybody in the club may read the member list; only a club admin may
     * change it. The bank details are the reason `view` is not the same thing
     * as `update` — MemberResource withholds them from a non-admin.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAdminRights();
    }

    public function update(User $user, Member $member): bool
    {
        return $user->hasAdminRights($member->club_id);
    }

    /**
     * Ending a membership is an update, not a deletion: the history stays.
     */
    public function resign(User $user, Member $member): bool
    {
        return $this->update($user, $member);
    }

    /**
     * Taking somebody back in is an update too, the mirror of resign().
     */
    public function rejoin(User $user, Member $member): bool
    {
        return $this->update($user, $member);
    }

    /**
     * Deleting is for a row that should never have existed, and only while
     * nothing hangs off it. Every table carrying a `member_id` is
     * ON DELETE CASCADE, so the database would silently take a member's whole
     * history with them — `isUsed()` is the only brake.
     *
     * Somebody who has actually been in the club therefore cannot be deleted:
     * they have sections, roles, honours. Recording that they left is
     * `resign()`. To delete anyway, strip the relations on the member page
     * first — which makes the loss deliberate and visible instead of silent.
     */
    public function delete(User $user, Member $member): bool
    {
        return $this->update($user, $member) && ! $member->isUsed();
    }
}

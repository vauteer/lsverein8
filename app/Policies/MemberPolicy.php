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
     * Deleting really deletes — `members` cascades into all six pivots, so a
     * member's whole history goes with them. Ending the membership
     * (`resign`) is the normal way somebody leaves; this is for a row that
     * should never have existed.
     */
    public function delete(User $user, Member $member): bool
    {
        return $this->update($user, $member);
    }
}

<?php

namespace App\Policies;

use App\Models\Debit;
use App\Models\User;

class DebitPolicy
{
    /**
     * Unlike subscriptions, a debit is not open to everybody in the club: a
     * row names one member and the money about to leave their account, so the
     * whole screen is a club admin's.
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
     * `debits` has no `club_id` of its own, so the club comes from the member
     * the row hangs off. MemberClubScope already 404s a foreign debit in route
     * model binding; this keeps the policy honest on its own.
     */
    public function update(User $user, Debit $debit): bool
    {
        return $user->hasAdminRights($debit->member->club_id);
    }

    /**
     * A debit is a one-off instruction that has not been collected yet —
     * nothing hangs off it, so there is no isUsed() branch here.
     */
    public function delete(User $user, Debit $debit): bool
    {
        return $this->update($user, $debit);
    }

    /**
     * Collecting writes a SEPA file for the whole club and clears every debit
     * it took along, so it is an admin action.
     */
    public function debit(User $user): bool
    {
        return $user->hasAdminRights();
    }
}

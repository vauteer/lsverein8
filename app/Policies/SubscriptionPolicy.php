<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * Everybody in the club may look at its subscriptions; only a club admin
     * may change them.
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
     * Subscriptions are never shared across the installation — `club_id` is
     * NOT NULL, so there is no root-only branch here. Sections, events and
     * roles could be shared until 2026-08-30, and no longer can.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return $user->hasAdminRights($subscription->club_id);
    }

    /**
     * Collecting the fees writes a SEPA file for the whole club, so it is an
     * admin action even though it changes no subscription of its own.
     */
    public function debit(User $user): bool
    {
        return $user->hasAdminRights();
    }

    /**
     * A subscription a member holds is kept. `member_subscription` is
     * ON DELETE CASCADE (unlike member_role, which is RESTRICT), so the
     * database would silently drop those assignments rather than refuse —
     * this check is the only thing standing in the way.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        return $this->update($user, $subscription) && ! $subscription->isUsed();
    }
}

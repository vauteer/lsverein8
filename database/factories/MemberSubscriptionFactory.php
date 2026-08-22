<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberSubscription;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberSubscription>
 */
class MemberSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'subscription_id' => Subscription::factory(),
        ];
    }
}

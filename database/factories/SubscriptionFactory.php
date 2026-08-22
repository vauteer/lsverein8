<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->unique()->words(2, true),
            'amount' => fake()->randomFloat(2, 10, 200),
            'transfer_text' => 'Beitrag <AJ> <VN> <NN>',
        ];
    }
}

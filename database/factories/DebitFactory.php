<?php

namespace Database\Factories;

use App\Models\Debit;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debit>
 */
class DebitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'amount' => fake()->randomFloat(2, 10, 200),
            'transfer_text' => 'Beitrag <AJ> <VN> <NN>',
            'due_at' => now(),
        ];
    }
}

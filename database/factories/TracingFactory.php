<?php

namespace Database\Factories;

use App\Enums\ActionType;
use App\Models\Tracing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tracing>
 */
class TracingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'at' => now(),
            'action_type' => ActionType::Login,
            'table_type' => null,
            'row_id' => null,
            'old_values' => null,
        ];
    }
}

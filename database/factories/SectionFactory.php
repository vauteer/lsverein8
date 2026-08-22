<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->unique()->words(2, true),
            'blsv_id' => fake()->unique()->numberBetween(1, 99),
        ];
    }
}

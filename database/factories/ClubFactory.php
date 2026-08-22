<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'street' => fake()->streetAddress(),
            'zipcode' => fake()->postcode(),
            'city' => fake()->city(),
            'blsv_member' => false,
            'bank' => fake()->company().' Bank',
            'account_owner' => fake()->name(),
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'sepa' => 'DE98ZZZ09999999999',
            'sepa_date' => now()->subYears(5),
            'display' => 1,
            'locale' => 'de',
            'honor_years' => '25,40,50,60',
            'use_items' => false,
        ];
    }

    public function blsvMember(): static
    {
        return $this->state(fn (array $attributes): array => ['blsv_member' => true]);
    }
}

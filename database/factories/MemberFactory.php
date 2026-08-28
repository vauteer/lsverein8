<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'club_id' => Club::factory(),
            'member_id' => fake()->unique()->numberBetween(1, 999999),
            'first_name' => fake()->firstName($gender),
            'surname' => fake()->lastName(),
            'gender' => substr($gender, 0, 1),
            'birthday' => fake()->dateTimeBetween('-80 years', '-3 years'),
            'street' => fake()->streetAddress(),
            'zipcode' => fake()->postcode(),
            'city' => fake()->city(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }

    public function ofClub(Club|int $club): static
    {
        return $this->state(fn (array $attributes): array => [
            'club_id' => $club instanceof Club ? $club->id : $club,
        ]);
    }

    public function born(string $birthday): static
    {
        return $this->state(fn (array $attributes): array => ['birthday' => $birthday]);
    }

    public function deceased(?string $deathDay = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'death_day' => $deathDay ?? now()->subYear(),
        ]);
    }

    /**
     * Pays by direct debit, so the SEPA generator picks the member up.
     *
     * The IBAN is what makes that true — `Member::payment_method` is derived
     * from it, there is no column to set since 2026-08-28. Without this state
     * a member has no bank details and is billed by hand.
     */
    public function payingByAccount(): static
    {
        return $this->state(fn (array $attributes): array => [
            'bank' => fake()->company(),
            'account_owner' => fake()->name(),
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
        ]);
    }
}

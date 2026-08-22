<?php

namespace Database\Factories;

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\ClubUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubUser>
 */
class ClubUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'role' => ClubRole::Basic->value,
        ];
    }
}

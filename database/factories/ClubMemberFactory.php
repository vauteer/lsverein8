<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMember>
 */
class ClubMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'member_id' => Member::factory(),
            'from' => now()->subYears(10),
            'to' => null,
        ];
    }
}

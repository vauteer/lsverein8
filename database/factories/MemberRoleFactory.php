<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberRole;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberRole>
 */
class MemberRoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'role_id' => Role::factory(),
            'from' => now()->subYears(2),
            'to' => null,
        ];
    }
}

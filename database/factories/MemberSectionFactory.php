<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberSection;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberSection>
 */
class MemberSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'section_id' => Section::factory(),
            'from' => now()->subYears(5),
            'to' => null,
        ];
    }
}

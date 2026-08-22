<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventMember>
 */
class EventMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'event_id' => Event::factory(),
            'date' => now()->subYear(),
        ];
    }
}

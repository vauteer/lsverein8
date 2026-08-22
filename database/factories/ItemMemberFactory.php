<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemMember>
 */
class ItemMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'member_id' => Member::factory(),
            'from' => now()->subYear(),
            'to' => null,
        ];
    }
}

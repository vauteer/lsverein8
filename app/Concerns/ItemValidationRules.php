<?php

namespace App\Concerns;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ItemValidationRules
{
    /**
     * Get the validation rules used to validate a club's inventory item.
     *
     * `club_id` is deliberately absent: it is set from the current club when
     * the item is created and never submitted, so a club admin cannot move an
     * item into another club.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function itemRules(?int $itemId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                // `items.club_id` is NOT NULL, so there are no shared rows to
                // consider and this matches the table's unique(club_id, name).
                Rule::unique(Item::class)
                    ->where(fn ($query) => $query->where('club_id', currentClubId()))
                    ->ignore($itemId),
            ],
        ];
    }

    /**
     * Get the validation messages for the item rules.
     *
     * @return array<string, mixed>
     */
    protected function itemMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'unique' => __(':attribute is already in use.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function itemAttributes(): array
    {
        return [
            'name' => __('Name'),
        ];
    }
}

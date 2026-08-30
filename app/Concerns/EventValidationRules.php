<?php

namespace App\Concerns;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait EventValidationRules
{
    /**
     * Get the validation rules used to validate a club's event.
     *
     * `club_id` is deliberately absent: it is set from the current club when
     * the event is created and never submitted, so a club admin cannot move an
     * event into another club.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function eventRules(?int $eventId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                // Scoped by hand: `unique` runs a plain query and inherits
                // no model scope. Another club's identical name is free — it
                // is never listed beside this club's.
                Rule::unique(Event::class)
                    ->where(fn ($query) => $query->where('club_id', currentClubId()))
                    ->ignore($eventId),
            ],
        ];
    }

    /**
     * Get the validation messages for the event rules.
     *
     * @return array<string, mixed>
     */
    protected function eventMessages(): array
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
    protected function eventAttributes(): array
    {
        return [
            'name' => __('Name'),
        ];
    }
}

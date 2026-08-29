<?php

namespace App\Concerns;

use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait SectionValidationRules
{
    /**
     * Get the validation rules used to validate a club's section.
     *
     * `club_id` is deliberately absent: it is set from the current club when
     * the section is created and never submitted, so a club admin cannot move
     * a section into another club or turn it into a shared one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function sectionRules(?int $sectionId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                // Deliberately unrestricted beyond the length: the BLSV export
                // escapes the name where it builds the path (Club::pathSafe()),
                // so a slash is the filesystem's problem, not the club's.
                Rule::unique(Section::class)
                    // Shared sections (club_id null) are listed alongside the
                    // club's own ones, so a duplicate name would show twice.
                    ->where(fn ($query) => $query
                        ->where(fn ($inner) => $inner
                            ->where('club_id', currentClubId())
                            ->orWhereNull('club_id')))
                    ->ignore($sectionId),
            ],
            'blsv_id' => [
                'nullable',
                'integer',
                Rule::in(array_keys(Section::BLSV_SECTIONS)),
                Rule::prohibitedIf(! currentClub()->blsv_member),
            ],
        ];
    }

    /**
     * Get the validation messages for the section rules.
     *
     * @return array<string, mixed>
     */
    protected function sectionMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'unique' => __(':attribute is already in use.'),
            'integer' => __(':attribute must be an integer.'),
            'in' => __('The selected :attribute is invalid.'),
            'prohibited' => __(':attribute is not available for this club.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sectionAttributes(): array
    {
        return [
            'name' => __('Name'),
            'blsv_id' => __('BLSV section'),
        ];
    }
}

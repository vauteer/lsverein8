<?php

namespace App\Concerns;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait RoleValidationRules
{
    /**
     * Get the validation rules used to validate a club's role.
     *
     * `club_id` is deliberately absent: it is set from the current club when
     * the role is created and never submitted, so a club admin cannot move a
     * role into another club or turn it into a shared one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function roleRules(?int $roleId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique(Role::class)
                    // Shared roles (club_id null) are listed alongside the
                    // club's own ones, so a duplicate name would show twice.
                    ->where(fn ($query) => $query
                        ->where(fn ($inner) => $inner
                            ->where('club_id', currentClubId())
                            ->orWhereNull('club_id')))
                    ->ignore($roleId),
            ],
        ];
    }

    /**
     * Get the validation messages for the role rules.
     *
     * @return array<string, mixed>
     */
    protected function roleMessages(): array
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
    protected function roleAttributes(): array
    {
        return [
            'name' => __('Name'),
        ];
    }
}

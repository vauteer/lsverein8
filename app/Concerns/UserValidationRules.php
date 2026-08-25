<?php

namespace App\Concerns;

use App\Enums\ClubRole;
use App\Enums\Locale;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait UserValidationRules
{
    /**
     * Get the validation rules used to validate a club's user.
     *
     * The `admin` column is deliberately absent: it is the global root flag,
     * not the per-club role, and letting a club admin submit it would be a
     * privilege escalation. Only the club_user `role` is editable here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function userRules(?int $userId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                $userId === null
                    ? Rule::unique(User::class)
                    : Rule::unique(User::class)->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:191'],
            // Null means "follow the club", so this is no longer required.
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'role' => ['required', 'integer', Rule::in(array_column(ClubRole::cases(), 'value'))],
        ];
    }

    /**
     * Get the validation messages for the user rules.
     *
     * @return array<string, mixed>
     */
    protected function userMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'email' => __(':attribute must be a valid email address.'),
            'unique' => __(':attribute is already in use.'),
            'integer' => __(':attribute must be an integer.'),
            'in' => __('The selected :attribute is invalid.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * Get the attribute names for the user rules.
     *
     * @return array<string, string>
     */
    protected function userAttributes(): array
    {
        return [
            'name' => __('Name'),
            'email' => __('Email address'),
            'phone' => __('Phone'),
            'locale' => __('Language'),
            'role' => __('Role'),
        ];
    }
}

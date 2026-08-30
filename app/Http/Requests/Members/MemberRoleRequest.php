<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberRelationRules;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One spell holding a role. `roles.club_id` is nullable, so the
 * installation-wide rows are valid here too.
 *
 * Store and update share one request: nothing here is unique, so the rules do
 * not differ between adding a row and correcting one.
 */
class MemberRoleRequest extends FormRequest
{
    use MemberRelationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_id' => $this->belongsToClubRule(Role::class),
            ...$this->rangeRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->relationMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->relationAttributes();
    }
}

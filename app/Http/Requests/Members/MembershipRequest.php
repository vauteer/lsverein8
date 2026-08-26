<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberRelationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One period of club membership. There is no club to pick: every club_member
 * row in production points at the member's own club, and another club's would be
 * invisible behind the ClubScope anyway.
 *
 * Store and update share one request: nothing here is unique, so the rules do
 * not differ between adding a row and correcting one.
 */
class MembershipRequest extends FormRequest
{
    use MemberRelationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->rangeRules();
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

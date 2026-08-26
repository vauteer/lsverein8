<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberRelationRules;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One item issued to the member for a period. `items.club_id` is NOT NULL, so
 * there are no shared rows to allow.
 *
 * Store and update share one request: nothing here is unique, so the rules do
 * not differ between adding a row and correcting one.
 */
class MemberItemRequest extends FormRequest
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
            'item_id' => $this->belongsToClubRule(Item::class),
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

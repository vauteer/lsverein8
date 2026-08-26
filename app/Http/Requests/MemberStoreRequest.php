<?php

namespace App\Http\Requests;

use App\Concerns\MemberValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new member carries the entry data on top of their own columns: the club
 * membership, the first section and optionally a subscription all start on the
 * same date.
 */
class MemberStoreRequest extends FormRequest
{
    use MemberValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->memberRules(), ...$this->entryRules()];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->memberMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->memberAttributes();
    }
}

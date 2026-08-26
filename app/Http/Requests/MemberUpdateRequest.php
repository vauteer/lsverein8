<?php

namespace App\Http\Requests;

use App\Concerns\MemberValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * No entry rules here: the membership, sections and subscriptions of an
 * existing member are managed through their own relations, not by re-posting
 * the joining date. The date of death is the other way round — it belongs to
 * somebody who is already a member, so only this form carries it.
 */
class MemberUpdateRequest extends FormRequest
{
    use MemberValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->memberRules(), ...$this->deathRules()];
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

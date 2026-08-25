<?php

namespace App\Http\Requests;

use App\Concerns\SectionValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SectionStoreRequest extends FormRequest
{
    use SectionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->sectionRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->sectionMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->sectionAttributes();
    }
}

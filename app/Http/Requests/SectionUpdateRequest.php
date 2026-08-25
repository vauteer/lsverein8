<?php

namespace App\Http\Requests;

use App\Concerns\SectionValidationRules;
use App\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SectionUpdateRequest extends FormRequest
{
    use SectionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Section $section */
        $section = $this->route('section');

        return $this->sectionRules($section->id);
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

<?php

namespace App\Http\Requests;

use App\Concerns\ItemValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ItemStoreRequest extends FormRequest
{
    use ItemValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->itemRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->itemMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->itemAttributes();
    }
}

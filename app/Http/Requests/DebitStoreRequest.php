<?php

namespace App\Http\Requests;

use App\Concerns\DebitValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DebitStoreRequest extends FormRequest
{
    use DebitValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->debitRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->debitMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->debitAttributes();
    }
}

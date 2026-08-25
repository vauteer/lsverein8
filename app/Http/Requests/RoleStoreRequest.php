<?php

namespace App\Http\Requests;

use App\Concerns\RoleValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleStoreRequest extends FormRequest
{
    use RoleValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->roleRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->roleMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->roleAttributes();
    }
}

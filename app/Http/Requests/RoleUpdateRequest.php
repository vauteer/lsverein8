<?php

namespace App\Http\Requests;

use App\Concerns\RoleValidationRules;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    use RoleValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->roleRules($role->id);
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

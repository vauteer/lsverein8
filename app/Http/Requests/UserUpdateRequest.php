<?php

namespace App\Http\Requests;

use App\Concerns\UserValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    use UserValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->userRules($user->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->userMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->userAttributes();
    }
}

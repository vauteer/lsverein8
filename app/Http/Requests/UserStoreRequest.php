<?php

namespace App\Http\Requests;

use App\Concerns\UserValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UserStoreRequest extends FormRequest
{
    use UserValidationRules;

    private ?User $existingUser = null;

    private bool $existingUserResolved = false;

    /**
     * When the email belongs to an existing account, only the email and the
     * club role are validated; the account's own columns are left untouched,
     * see UserController::store().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->existingUser() === null
            ? $this->userRules()
            : $this->existingUserRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $existingUser = $this->existingUser();

            if ($existingUser !== null && $existingUser->clubs()->whereKey(currentClubId())->exists()) {
                $validator->errors()->add('email', __('This user already belongs to your club.'));
            }
        });
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

    /**
     * The user account already registered under the submitted email, if any.
     */
    public function existingUser(): ?User
    {
        if (! $this->existingUserResolved) {
            $this->existingUserResolved = true;
            $email = $this->input('email');
            $this->existingUser = is_string($email) ? User::where('email', $email)->first() : null;
        }

        return $this->existingUser;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function existingUserRules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => $this->userRules()['role'],
        ];
    }
}

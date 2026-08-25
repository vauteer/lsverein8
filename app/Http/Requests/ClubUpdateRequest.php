<?php

namespace App\Http\Requests;

use App\Concerns\ClubValidationRules;
use App\Models\Club;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClubUpdateRequest extends FormRequest
{
    use ClubValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Club $club */
        $club = $this->route('club');

        return $this->clubRules($club->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->clubMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->clubAttributes();
    }
}

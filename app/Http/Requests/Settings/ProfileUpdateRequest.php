<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\LandingPage;
use App\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            // Null means "follow the club", as in the user CRUD.
            'locale' => ['nullable', Rule::enum(Locale::class)],
            // Required, unlike locale: there is no club-level landing page to
            // fall back on, so an empty value would mean nothing.
            'landing_page' => ['required', Rule::enum(LandingPage::class)],
            'profile_image' => ['nullable', 'image', 'max:2048'],
            'remove_profile_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return [
            'profile_image.image' => __('The profile photo must be an image.'),
            'profile_image.max' => __('The profile photo may not be larger than 2 MB.'),
        ];
    }
}

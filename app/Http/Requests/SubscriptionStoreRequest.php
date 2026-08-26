<?php

namespace App\Http\Requests;

use App\Concerns\SubscriptionValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionStoreRequest extends FormRequest
{
    use SubscriptionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->subscriptionRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->subscriptionMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->subscriptionAttributes();
    }
}

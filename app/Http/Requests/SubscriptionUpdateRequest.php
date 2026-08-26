<?php

namespace App\Http\Requests;

use App\Concerns\SubscriptionValidationRules;
use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionUpdateRequest extends FormRequest
{
    use SubscriptionValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Subscription $subscription */
        $subscription = $this->route('subscription');

        return $this->subscriptionRules($subscription->id);
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

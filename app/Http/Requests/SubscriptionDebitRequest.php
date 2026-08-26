<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionDebitRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subscriptions' => ['required', 'array', 'min:1'],
            'subscriptions.*' => [
                'integer',
                // Scoped to the club by hand: `exists` runs a plain query and
                // does not pick up the model's ClubScope, so without the
                // where() a club admin could collect another club's fees.
                //
                // `amount > 0` mirrors what the dialog offers: a 0 € fee has
                // nothing to collect and would only add an empty line to the
                // SEPA file.
                Rule::exists(Subscription::class, 'id')
                    ->where(fn ($query) => $query
                        ->where('club_id', currentClubId())
                        ->where('amount', '>', 0)),
            ],
            'date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return [
            'subscriptions.required' => __('Select at least one subscription to collect.'),
            'subscriptions.*.exists' => __('The selected :attribute is invalid.'),
            'date.required' => __(':attribute is required.'),
            'date.date' => __(':attribute must be a valid date.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subscriptions' => __('Subscriptions'),
            'subscriptions.*' => __('Subscription'),
            'date' => __('Execution date'),
        ];
    }
}

<?php

namespace App\Concerns;

use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait SubscriptionValidationRules
{
    /**
     * Get the validation rules used to validate a club's subscription.
     *
     * `club_id` is deliberately absent: it is set from the current club when
     * the subscription is created and never submitted, so a club admin cannot
     * move a subscription into another club.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function subscriptionRules(?int $subscriptionId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                // No shared rows to consider (ClubScope), so the rule matches
                // the table's unique(club_id, name) exactly.
                Rule::unique(Subscription::class)
                    ->where(fn ($query) => $query->where('club_id', currentClubId()))
                    ->ignore($subscriptionId),
            ],
            // `subscriptions.amount` is decimal(8,2); the upper bound keeps a
            // typo out of the database rather than letting it fail there.
            'amount' => ['required', 'numeric', 'between:0,999999.99'],
            'transfer_text' => [
                'required',
                'string',
                'max:191',
                'regex:'.TRANSFER_TEXT_REGEX,
            ],
            'memo' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Get the validation messages for the subscription rules.
     *
     * @return array<string, mixed>
     */
    protected function subscriptionMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'unique' => __(':attribute is already in use.'),
            'numeric' => __(':attribute must be a number.'),
            'between' => [
                'numeric' => __(':attribute must be between :min and :max.'),
            ],
            'transfer_text.regex' => __('The transfer text may not contain umlauts or other special characters; the SEPA format does not allow them.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function subscriptionAttributes(): array
    {
        return [
            'name' => __('Name'),
            'amount' => __('Amount'),
            'transfer_text' => __('Transfer text'),
            'memo' => __('Memo'),
        ];
    }
}

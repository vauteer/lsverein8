<?php

namespace App\Http\Requests\Members;

use App\Concerns\MemberRelationRules;
use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One subscription the member holds. `member_subscription` carries no dates at
 * all, which is why the member list cannot answer "who held this in 2019".
 *
 * Store and update share one request: nothing here is unique, so the rules do
 * not differ between adding a row and correcting one.
 */
class MemberSubscriptionRequest extends FormRequest
{
    use MemberRelationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subscription_id' => $this->belongsToClubRule(Subscription::class),
            ...$this->memoRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return $this->relationMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->relationAttributes();
    }
}

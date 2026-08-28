<?php

namespace App\Http\Requests;

use App\Concerns\MemberValidationRules;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Taking a former member back in, the mirror of MemberResignRequest.
 *
 * A section and a subscription come with the date, not afterwards: a current
 * member has to be in a section (BLSV clubs) and hold a subscription, and
 * reopening the membership on its own would create somebody who satisfies
 * neither. That gap is exactly what this closes — `MembershipController` can
 * still reopen a row without either, which is why rejoining has its own way in.
 */
class MemberRejoinRequest extends FormRequest
{
    use MemberValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');

        $lastEnd = $member->lastMembershipEnd();

        return [
            'date' => [
                'required',
                'date',
                'before_or_equal:'.now()->addMonths(3)->toDateString(),
                // Strictly after the last membership ended, so the two periods
                // cannot overlap or meet on the same day. `Member::
                // membershipYears()` sums the periods; an overlap would count
                // the same year twice.
                ...$lastEnd === null ? [] : ['after:'.$lastEnd->format('Y-m-d')],
            ],
            ...$this->joiningRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return [
            'date.required' => __(':attribute is required.'),
            'date.date' => __(':attribute must be a valid date.'),
            'date.after' => __('The previous membership ended on :date, so this has to be later.'),
            'date.before_or_equal' => __('That is too far in the future.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('Rejoined on'),
            'section_id' => __('Section'),
            'subscription_id' => __('Subscription'),
        ];
    }
}

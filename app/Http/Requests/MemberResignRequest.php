<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ending a membership closes every open club membership and section of the
 * member on one date, so the date is the whole instruction.
 */
class MemberResignRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');

        $latestStart = $member->latestOpenStart();

        return [
            'date' => [
                'required',
                'date',
                // Strictly after, so the shortest membership this can produce
                // is one day. Ending one on the day it began is all but always
                // a slip; a single row can still be given an equal from/to
                // through its own dialog, which is a deliberate act.
                //
                // Against the latest open start rather than entry(): a member
                // who rejoined, or a section that started later, would
                // otherwise get a `to` before its own `from`.
                ...$latestStart === null ? [] : ['after:'.$latestStart->format('Y-m-d')],
            ],
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
            'date.after' => __('The membership must last at least one day, so this has to be after :date.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('Left on'),
        ];
    }
}

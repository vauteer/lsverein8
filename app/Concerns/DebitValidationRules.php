<?php

namespace App\Concerns;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait DebitValidationRules
{
    /**
     * Get the validation rules used to validate a one-off direct debit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function debitRules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                // Exactly the set DebitController::memberOptions() offers, so
                // the picker and the rule can never disagree.
                //
                // Scoped to the club by hand: `exists` runs a plain query and
                // does not pick up Member's ClubScope, so without the where()
                // a club admin could bill another club's member.
                //
                // The IBAN is what makes a member collectable at all; a debit
                // for somebody without one would only add an empty line to the
                // SEPA file. Membership is deliberately NOT required — a debit
                // is most useful precisely for somebody who has just left.
                Rule::exists(Member::class, 'id')
                    ->where(fn ($query) => $query
                        ->where('club_id', currentClubId())
                        ->where('iban', '<>', '')),
            ],
            // `debits.amount` is decimal(8,2). Unlike a subscription, which may
            // be 0 € for an honorary member, a debit is an instruction to move
            // money and there is nothing to instruct at 0.
            'amount' => ['required', 'numeric', 'between:0.01,999999.99'],
            'transfer_text' => [
                'required',
                'string',
                'max:191',
                'regex:'.TRANSFER_TEXT_REGEX,
            ],
            'due_at' => ['required', 'date'],
        ];
    }

    /**
     * Get the validation messages for the debit rules.
     *
     * @return array<string, mixed>
     */
    protected function debitMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'numeric' => __(':attribute must be a number.'),
            'date' => __(':attribute must be a valid date.'),
            'between' => [
                'numeric' => __(':attribute must be between :min and :max.'),
            ],
            'member_id.exists' => __('Pick a member of this club who has a bank account on file.'),
            'transfer_text.regex' => __('The transfer text may not contain umlauts or other special characters; the SEPA format does not allow them.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function debitAttributes(): array
    {
        return [
            'member_id' => __('Member'),
            'amount' => __('Amount'),
            'transfer_text' => __('Transfer text'),
            'due_at' => __('Due on'),
        ];
    }
}

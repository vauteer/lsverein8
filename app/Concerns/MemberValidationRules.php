<?php

namespace App\Concerns;

use App\Enums\Gender;
use App\Models\Section;
use App\Models\Subscription;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MemberValidationRules
{
    use ValidatesIban;

    /**
     * Get the validation rules used to validate a member.
     *
     * `club_id` and `member_id` are deliberately absent: the club comes from
     * the current one and the member number is handed out by the controller,
     * so neither is ever submitted.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function memberRules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:191'],
            'first_name' => ['required', 'string', 'max:191'],
            // ->only(): Divers is parked until the BLSV statistic can carry
            // a third value, so it must be refused here too and not merely
            // left out of the picker.
            'gender' => ['required', Rule::enum(Gender::class)->only(Gender::selectable())],
            'birthday' => ['required', 'date', 'before_or_equal:today'],
            'street' => ['required', 'string', 'max:191'],
            'zipcode' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:191'],
            // The bank details are all-or-nothing. There is no payment method
            // to key them off any more (the column went on 2026-08-28); an
            // IBAN on file IS the statement "collect from this member", and
            // `Subscription::generateSepa()` writes account owner, IBAN and
            // BIC into the XML together. A half-filled set would reach the
            // bank as a payment without a debtor.
            //
            // `bank` rides along for completeness: it is not in the XML, but a
            // record carrying three of the four fields is a record somebody
            // abandoned halfway.
            'bank' => ['nullable', 'string', 'max:191', 'required_with:account_owner,iban,bic'],
            'account_owner' => [
                'nullable',
                'string',
                'max:191',
                'required_with:bank,iban,bic',
                // Goes into the SEPA XML as the debtor's name, so it is held
                // to the plain SEPA set — no placeholders here, unlike a
                // transfer text.
                'regex:'.SEPA_CHARSET_REGEX,
            ],
            'iban' => ['nullable', 'string', 'required_with:bank,account_owner,bic', $this->ibanRule()],
            'bic' => ['nullable', 'string', 'required_with:bank,account_owner,iban', 'regex:'.BIC_REGEX],
            'memo' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * The date of death, which only the edit form carries.
     *
     * Nobody is entered into the club dead, so offering the field on the
     * create form was both useless and grim. It is recorded afterwards, on
     * somebody who is already a member.
     *
     * `after:birthday` catches the transposed pair that would otherwise give a
     * negative age.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function deathRules(): array
    {
        return [
            'death_day' => ['nullable', 'date', 'after:birthday', 'before_or_equal:today'],
        ];
    }

    /**
     * The extra fields a new member carries: when they joined, which section
     * they join, and what they pay. lsverein7 validated these separately from
     * the member's own columns and so does this — they are written to pivots,
     * never to `members`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function entryRules(): array
    {
        return [
            // Three months ahead, not `today`: a club does enter somebody who
            // joins on 1 September, and refusing that only makes the user put
            // today's date in, which falsifies the membership years. The bound
            // is there to catch a mistyped year, nothing more.
            'entry_date' => ['required', 'date', 'before_or_equal:'.now()->addMonths(3)->toDateString()],
            ...$this->joiningRules(),
        ];
    }

    /**
     * The section and subscription a membership cannot be without.
     *
     * Shared by joining (`entryRules()`) and rejoining (MemberRejoinRequest):
     * both open a membership, and both invariants have to hold from that
     * moment, not from whenever somebody gets round to filling them in.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function joiningRules(): array
    {
        return [
            // Scoped by hand: `exists` runs a plain query and does not pick up
            // the model's ClubScope, so without the where() a new member could
            // be filed under another club's section.
            'section_id' => [
                'required',
                'integer',
                Rule::exists(Section::class, 'id')
                    ->where(fn ($query) => $query
                        ->where('club_id', currentClubId())
                        ->orWhereNull('club_id')),
            ],
            // Required, like section_id: a current member has to hold a
            // subscription so that the club's billing is the sum over them and
            // nobody is invisible in it. Paying nothing is a 0 € subscription,
            // which names the reason. MemberSubscriptionController guards the
            // other end.
            'subscription_id' => [
                'required',
                'integer',
                Rule::exists(Subscription::class, 'id')
                    ->where(fn ($query) => $query->where('club_id', currentClubId())),
            ],
        ];
    }

    /**
     * Get the validation messages for the member rules.
     *
     * @return array<string, mixed>
     */
    protected function memberMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'required_if' => __(':attribute is required when collecting by direct debit.'),
            'string' => __(':attribute must be a string.'),
            'integer' => __(':attribute must be a number.'),
            'date' => __(':attribute must be a valid date.'),
            'email' => __(':attribute must be a valid email address.'),
            'enum' => __('The selected :attribute is invalid.'),
            'exists' => __('The selected :attribute is invalid.'),
            'before_or_equal' => __(':attribute may not be in the future.'),
            // The generic line above is right for a birthday and a date of
            // death; a joining date may be ahead, just not far.
            'entry_date.before_or_equal' => __(':attribute may be at most three months ahead.'),
            'death_day.after' => __(':attribute must be after the date of birth.'),
            'account_owner.regex' => __(':attribute may only contain characters the SEPA format allows.'),
            'bic.regex' => __('The BIC is invalid.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function memberAttributes(): array
    {
        return [
            'surname' => __('Surname'),
            'first_name' => __('First name'),
            'gender' => __('Gender'),
            'birthday' => __('Date of birth'),
            'death_day' => __('Date of death'),
            'street' => __('Street'),
            'zipcode' => __('Postcode'),
            'city' => __('City'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'bank' => __('Bank'),
            'account_owner' => __('Account owner'),
            'iban' => __('IBAN'),
            'bic' => __('BIC'),
            'memo' => __('Memo'),
            'entry_date' => __('Joined on'),
            'section_id' => __('Section'),
            'subscription_id' => __('Subscription'),
        ];
    }
}

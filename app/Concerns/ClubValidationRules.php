<?php

namespace App\Concerns;

use App\Enums\ClubIdentityDisplay;
use App\Enums\Locale;
use App\Models\Club;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ClubValidationRules
{
    use ValidatesIban;

    /**
     * Get the validation rules used to validate a club.
     *
     * `logo` is the uploaded file, not the stored filename — the controller
     * writes the column itself, so it must never be mass-assigned.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function clubRules(?int $clubId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                // The name goes into the SEPA XML as the creditor, which only
                // accepts this character set.
                'regex:'.SEPA_CHARSET_REGEX,
                Rule::unique(Club::class)->ignore($clubId),
            ],
            'street' => ['required', 'string', 'max:191'],
            'zipcode' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:191'],
            'bank' => ['required', 'string', 'max:191'],
            'account_owner' => ['required', 'string', 'max:191', 'regex:'.SEPA_CHARSET_REGEX],
            'iban' => ['required', 'string', $this->ibanRule()],
            'bic' => ['required', 'string', 'regex:'.BIC_REGEX],
            'sepa_creditor_id' => ['nullable', 'string', 'max:191'],
            'sepa_mandate_date' => ['nullable', 'date'],
            // How far ahead the two collection dialogs default their execution
            // date. The bank's lead time, so it is the club's to set; neither
            // collection enforces it, because a treasurer sometimes has to
            // book a date the rule would not allow.
            'sepa_lead_days' => ['required', 'integer', 'between:0,60'],
            'identity_display' => ['required', Rule::enum(ClubIdentityDisplay::class)],
            'locale' => ['required', Rule::enum(Locale::class)],
            // A comma separated list of membership years that trigger an
            // honour, e.g. "25,40,50". Member::honorYearReached() splits on it.
            'honor_years' => ['nullable', 'string', 'regex:/^\d{1,2}(,\d{1,2})*$/'],
            'blsv_member' => ['boolean'],
            'use_items' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validation messages for the club rules.
     *
     * @return array<string, mixed>
     */
    protected function clubMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'string' => __(':attribute must be a string.'),
            'unique' => __(':attribute is already in use.'),
            'boolean' => __(':attribute must be true or false.'),
            'date' => __(':attribute must be a valid date.'),
            'in' => __('The selected :attribute is invalid.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
            'name.regex' => __(':attribute may only contain characters the SEPA format allows.'),
            'account_owner.regex' => __(':attribute may only contain characters the SEPA format allows.'),
            'bic.regex' => __('The BIC is invalid.'),
            'honor_years.regex' => __(':attribute must be a comma separated list of years, e.g. 25,40,50.'),
            'logo.image' => __('The logo must be an image.'),
            'logo.max' => __('The logo may not be larger than 2 MB.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clubAttributes(): array
    {
        return [
            'name' => __('Name'),
            'street' => __('Street'),
            'zipcode' => __('Postcode'),
            'city' => __('City'),
            'bank' => __('Bank'),
            'account_owner' => __('Account owner'),
            'iban' => __('IBAN'),
            'bic' => __('BIC'),
            'sepa_creditor_id' => __('SEPA creditor identifier'),
            'sepa_lead_days' => __('SEPA lead time in days'),
            'sepa_mandate_date' => __('SEPA mandate date'),
            'identity_display' => __('Display'),
            'locale' => __('Language'),
            'honor_years' => __('Honour after years of membership'),
            'blsv_member' => __('BLSV member'),
            'use_items' => __('Use inventory'),
            'logo' => __('Logo'),
        ];
    }
}

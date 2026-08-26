<?php

namespace App\Concerns;

use Closure;

trait ValidatesIban
{
    /**
     * Checksum validation for an IBAN. A closure rather than a class in
     * app/Rules, which this app does not have.
     */
    protected function ibanRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! checkIban($value)) {
                $fail(__('The IBAN is invalid.'))->translate();
            }
        };
    }
}

<?php

namespace App\Enums;

/**
 * How the club gets what a member owes it.
 *
 * Not a column: `Member::payment_method` derives this from whether an IBAN is
 * on file, because that is the only thing that decides between a SEPA line and
 * an outstanding payment. `members.payment_method` was dropped on 2026-08-28.
 *
 * The backing values are the old column's and stay: they are the URL part of
 * the member list's `payment_k` / `payment_r` selection, which lives in
 * bookmarks.
 *
 * There is deliberately no third case for somebody who pays nothing. That is
 * a 0 € subscription now — "Familienmitglied" or "Beitragsfrei" — which says
 * why rather than only that they are not billed.
 */
enum PaymentMethod: string
{
    case Account = 'k';
    case Invoice = 'r';

    public function label(): string
    {
        return match ($this) {
            self::Account => __('Direct debit'),
            self::Invoice => __('Invoice'),
        };
    }

    /**
     * Whether the club collects from this member by direct debit, which is
     * what decides between a SEPA line and an outstanding payment.
     */
    public function isCollectable(): bool
    {
        return $this === self::Account;
    }

    /**
     * The methods as {id, name} options for the frontend.
     *
     * Only the member list's selection uses these — the member form has no
     * picker any more, the bank details decide.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $method): array => ['id' => $method->value, 'name' => $method->label()],
            self::cases()
        );
    }
}

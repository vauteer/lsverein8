<?php

namespace App\Enums;

/**
 * How a member settles what they owe the club, backing `members.payment_method`.
 *
 * Only `Account` produces a SEPA line; everybody else lands on the outstanding
 * list of a collection run and is billed by hand.
 */
enum PaymentMethod: string
{
    case Account = 'k';
    case Invoice = 'r';
    case NonPayer = 'n';

    public function label(): string
    {
        return match ($this) {
            self::Account => __('Direct debit'),
            self::Invoice => __('Invoice'),
            self::NonPayer => __('Does not pay'),
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
     * The selectable methods, as {id, name} options for the frontend.
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

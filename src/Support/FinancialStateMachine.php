<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use BackedEnum;
use Karnoweb\Commerce\Exceptions\InvalidFinancialTransition;

/**
 * Strict financial transitions for orders (and invoice financial_status
 * where the same vocabulary applies). Same-status is a no-op so a second
 * confirmed payment on an already-paid order does not throw.
 *
 * Allowed: pending → paid | cancelled; paid → refunded;
 * issued → paid | cancelled (invoices).
 */
final class FinancialStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
        'issued' => ['paid', 'cancelled'],
    ];

    public static function assertCanTransition(BackedEnum|string $from, BackedEnum|string $to): void
    {
        $fromValue = $from instanceof BackedEnum ? (string) $from->value : $from;
        $toValue = $to instanceof BackedEnum ? (string) $to->value : $to;

        if ($fromValue === $toValue) {
            return;
        }

        $allowed = self::TRANSITIONS[$fromValue] ?? [];

        if (! in_array($toValue, $allowed, true)) {
            throw new InvalidFinancialTransition($fromValue, $toValue);
        }
    }

    public static function canTransition(BackedEnum|string $from, BackedEnum|string $to): bool
    {
        try {
            self::assertCanTransition($from, $to);

            return true;
        } catch (InvalidFinancialTransition) {
            return false;
        }
    }
}

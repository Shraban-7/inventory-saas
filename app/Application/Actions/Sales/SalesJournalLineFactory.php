<?php

namespace App\Application\Actions\Sales;

use App\Application\DTOs\JournalEntryLineData;
use App\Domain\Entities\Money;
use App\Domain\Entities\Totals;
use App\Domain\Exceptions\InvalidJournalEntryException;

final class SalesJournalLineFactory
{
    /**
     * @param  array<string, int>  $accounts
     * @return list<JournalEntryLineData>
     */
    public static function invoice(Totals $totals, array $accounts, bool $reverse = false): array
    {
        $lines = [];
        self::append($lines, self::account($accounts, '1200'), $reverse ? Money::zero() : $totals->total, $reverse ? $totals->total : Money::zero(), 'Accounts receivable');
        self::append($lines, self::account($accounts, '4000'), $reverse ? $totals->subtotal : Money::zero(), $reverse ? Money::zero() : $totals->subtotal, 'Sales revenue');

        $taxes = [];

        foreach ($totals->lines as $line) {
            if ($line->taxAccountId !== null) {
                $taxes[$line->taxAccountId] = ($taxes[$line->taxAccountId] ?? Money::zero())->add($line->tax);
            }
        }

        ksort($taxes);

        foreach ($taxes as $accountId => $amount) {
            self::append($lines, $accountId, $reverse ? $amount : Money::zero(), $reverse ? Money::zero() : $amount, 'Sales tax');
        }

        self::append($lines, self::account($accounts, '5000'), $reverse ? Money::zero() : $totals->cost, $reverse ? $totals->cost : Money::zero(), 'Cost of goods sold');
        self::append($lines, self::account($accounts, '1300'), $reverse ? $totals->cost : Money::zero(), $reverse ? Money::zero() : $totals->cost, 'Inventory');

        return $lines;
    }

    /** @param array<string, int> $accounts */
    private static function account(array $accounts, string $code): int
    {
        foreach ($accounts as $accountCode => $accountId) {
            if ((string) $accountCode === $code) {
                return $accountId;
            }
        }

        throw new InvalidJournalEntryException("System account {$code} was not resolved.");
    }

    /** @param list<JournalEntryLineData> $lines */
    private static function append(array &$lines, int $accountId, Money $debit, Money $credit, string $description): void
    {
        if ($debit->isZero() && $credit->isZero()) {
            return;
        }

        $lines[] = new JournalEntryLineData(
            $accountId,
            $debit->toDecimal(),
            $credit->toDecimal(),
            $description,
        );
    }
}

<?php

namespace App\Application\Actions\Purchasing;

use App\Application\DTOs\JournalEntryLineData;
use App\Domain\Entities\BillTotals;
use App\Domain\Entities\Money;
use App\Domain\Exceptions\InvalidJournalEntryException;

final class PurchasingJournalLineFactory
{
    /** @param array<string, int> $accounts
     * @return list<JournalEntryLineData>
     */
    public static function bill(BillTotals $totals, array $accounts, bool $linkedToGrn): array
    {
        $lines = [];
        self::append($lines, self::account($accounts, $linkedToGrn ? '2050' : '6000'), $totals->gross, Money::zero(), $linkedToGrn ? 'Clear GRNI' : 'Purchase expense');

        $taxes = [];

        foreach ($totals->lines as $line) {
            if ($line->taxAccountId !== null) {
                $taxes[$line->taxAccountId] = ($taxes[$line->taxAccountId] ?? Money::zero())->add($line->tax);
            }
        }

        ksort($taxes);

        foreach ($taxes as $accountId => $amount) {
            self::append($lines, $accountId, $amount, Money::zero(), 'Purchase tax');
        }

        self::append($lines, self::account($accounts, '2000'), Money::zero(), $totals->total, 'Accounts payable');

        return $lines;
    }

    /** @param array<string, int> $accounts
     * @return list<JournalEntryLineData>
     */
    public static function receipt(Money $amount, array $accounts): array
    {
        $lines = [];
        self::append($lines, self::account($accounts, '1300'), $amount, Money::zero(), 'Inventory received');
        self::append($lines, self::account($accounts, '2050'), Money::zero(), $amount, 'Goods received not invoiced');

        return $lines;
    }

    /** @param array<string, int> $accounts
     * @return list<JournalEntryLineData>
     */
    public static function payment(Money $amount, array $accounts): array
    {
        $lines = [];
        self::append($lines, self::account($accounts, '2000'), $amount, Money::zero(), 'Accounts payable');
        self::append($lines, self::account($accounts, '1100'), Money::zero(), $amount, 'Cash');

        return $lines;
    }

    /** @param array<string, int> $accounts
     * @return list<JournalEntryLineData>
     */
    public static function supplierReturn(Money $amount, array $accounts, bool $linkedToBill): array
    {
        $lines = [];
        self::append($lines, self::account($accounts, $linkedToBill ? '2000' : '2050'), $amount, Money::zero(), $linkedToBill ? 'Accounts payable credit' : 'Reverse GRNI');
        self::append($lines, self::account($accounts, '1300'), Money::zero(), $amount, 'Inventory returned');

        return $lines;
    }

    /** @param array<string, int> $accounts */
    private static function account(array $accounts, string $code): int
    {
        return $accounts[$code] ?? throw new InvalidJournalEntryException("System account {$code} was not resolved.");
    }

    /** @param list<JournalEntryLineData> $lines */
    private static function append(array &$lines, int $accountId, Money $debit, Money $credit, string $description): void
    {
        if ($debit->isZero() && $credit->isZero()) {
            return;
        }

        $lines[] = new JournalEntryLineData($accountId, $debit->toDecimal(), $credit->toDecimal(), $description);
    }
}

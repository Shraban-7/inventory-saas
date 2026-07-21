<?php

namespace App\Application\Services;

use App\Domain\Exceptions\InvalidJournalEntryException;
use App\Infrastructure\Models\ChartOfAccount;

final class SystemChartOfAccountResolver
{
    public function id(string $code): int
    {
        return $this->ids([$code])[$code];
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    public function ids(array $codes): array
    {
        $codes = array_values(array_unique($codes));

        if ($codes === []) {
            return [];
        }

        $accounts = ChartOfAccount::query()
            ->whereIn('code', $codes)
            ->where('is_system', true)
            ->get(['id', 'code']);

        $resolved = [];

        foreach ($accounts as $account) {
            $resolved[$account->code] = (int) $account->getKey();
        }

        if (count($resolved) !== count($codes)) {
            throw new InvalidJournalEntryException('A required system chart-of-account code is missing.');
        }

        return $resolved;
    }
}

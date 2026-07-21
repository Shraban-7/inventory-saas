<?php

namespace App\Application\Listeners;

use App\Domain\Entities\ChartOfAccountType;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\Tenant;

class SeedDefaultChartOfAccounts
{
    /** @var list<array{code: string, name: string, type: ChartOfAccountType}> */
    private const ACCOUNTS = [
        ['code' => '1100', 'name' => 'Cash/Bank', 'type' => ChartOfAccountType::Asset],
        ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => ChartOfAccountType::Asset],
        ['code' => '1300', 'name' => 'Inventory Asset', 'type' => ChartOfAccountType::Asset],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => ChartOfAccountType::Liability],
        ['code' => '2050', 'name' => 'Goods Received Not Invoiced', 'type' => ChartOfAccountType::Liability],
        ['code' => '2100', 'name' => 'Tax Payable', 'type' => ChartOfAccountType::Liability],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => ChartOfAccountType::Revenue],
        ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccountType::CostOfGoodsSold],
        ['code' => '6000', 'name' => 'Purchase Expense', 'type' => ChartOfAccountType::Expense],
    ];

    public function handle(Tenant $tenant): void
    {
        $previousTenant = app()->bound('current_tenant')
            ? app()->make('current_tenant')
            : null;

        app()->instance('current_tenant', $tenant);

        try {
            foreach (self::ACCOUNTS as $account) {
                ChartOfAccount::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->getKey(),
                        'code' => $account['code'],
                    ],
                    [
                        'name' => $account['name'],
                        'type' => $account['type'],
                        'is_system' => true,
                    ],
                );
            }
        } finally {
            if ($previousTenant instanceof Tenant) {
                app()->instance('current_tenant', $previousTenant);
            } else {
                app()->forgetInstance('current_tenant');
            }
        }
    }
}

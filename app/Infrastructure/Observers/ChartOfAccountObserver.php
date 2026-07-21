<?php

namespace App\Infrastructure\Observers;

use App\Domain\Repositories\ChartOfAccountTreeRepository;
use App\Infrastructure\Models\ChartOfAccount;

final readonly class ChartOfAccountObserver
{
    public function __construct(
        private ChartOfAccountTreeRepository $trees,
    ) {}

    public function created(ChartOfAccount $account): void
    {
        $this->invalidate($account);
    }

    public function updated(ChartOfAccount $account): void
    {
        $this->invalidate($account);
    }

    public function deleted(ChartOfAccount $account): void
    {
        $this->invalidate($account);
    }

    private function invalidate(ChartOfAccount $account): void
    {
        $this->trees->invalidate((int) $account->tenant_id);
    }
}

<?php

namespace App\Application\Actions\Accounting;

use App\Infrastructure\Models\AccountingPeriod;
use App\Infrastructure\Models\AuditLog;
use Illuminate\Support\Facades\DB;

final class LockAccountingPeriodAction
{
    public function handle(int $periodId, int $actingUserId): AccountingPeriod
    {
        return DB::transaction(function () use ($periodId, $actingUserId): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($periodId);

            if ($period->is_locked) {
                return $period;
            }

            $lockedAt = now();
            $period->forceFill([
                'is_locked' => true,
                'locked_at' => $lockedAt,
                'locked_by_user_id' => $actingUserId,
            ])->save();

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'ACCOUNTING_PERIOD_LOCKED',
                'entity_type' => 'accounting_period',
                'entity_id' => $period->getKey(),
                'old_values' => ['is_locked' => false],
                'new_values' => [
                    'is_locked' => true,
                    'locked_at' => $lockedAt->toISOString(),
                    'locked_by_user_id' => $actingUserId,
                ],
            ]);

            return $period;
        });
    }
}

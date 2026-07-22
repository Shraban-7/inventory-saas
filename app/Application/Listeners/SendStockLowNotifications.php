<?php

namespace App\Application\Listeners;

use App\Application\Services\BranchAuthorizationService;
use App\Domain\Events\StockLow;
use App\Infrastructure\Models\User;
use App\Notifications\StockLowNotification;

class SendStockLowNotifications
{
    public function __construct(
        private readonly BranchAuthorizationService $authorization,
    ) {}

    public function handle(StockLow $event): void
    {
        $notification = StockLowNotification::fromEvent($event);

        User::query()
            ->where('tenant_id', $event->tenantId)
            ->whereHas('roles.permissions', static fn ($query) => $query->where('name', 'stock.adjust'))
            ->with(['roles.permissions'])
            ->eachById(function (User $user) use ($event, $notification): void {
                if ((int) $user->tenant_id !== $event->tenantId) {
                    return;
                }

                if (! $this->authorization->allows($user, 'stock.adjust', [$event->branchId])) {
                    return;
                }

                $user->notify($notification);
            });
    }
}

<?php

namespace App\Notifications;

use App\Domain\Events\StockLow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockLowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $variantId,
        public readonly int $branchId,
        public readonly string $quantityOnHand,
        public readonly int $reorderPoint,
        public readonly int $stockMovementId,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'variant_id' => $this->variantId,
            'branch_id' => $this->branchId,
            'quantity_on_hand' => $this->quantityOnHand,
            'reorder_point' => $this->reorderPoint,
            'stock_movement_id' => $this->stockMovementId,
        ];
    }

    public static function fromEvent(StockLow $event): self
    {
        return new self(
            $event->variantId,
            $event->branchId,
            $event->quantityOnHand,
            $event->reorderPoint,
            $event->stockMovementId,
        );
    }
}

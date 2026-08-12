<?php
namespace App\Listeners;
use App\Events\MenuUpdated;
use App\Services\SseEventService;

class BroadcastMenuUpdated
{
    public function handle(MenuUpdated $event): void
    {
        SseEventService::dispatch('menu.updated', [
            'action'       => $event->action,
            'item_id'      => $event->itemId,
            'item_name'    => $event->itemName,
            'new_price'    => $event->newPrice,
            'is_available' => $event->isAvailable,
            'updated_at'   => now()->toIso8601String(),
        ], $event->itemId);
    }
}

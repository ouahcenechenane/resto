<?php
namespace App\Listeners;
use App\Events\OrderCancelled;
use App\Services\SseEventService;

class BroadcastOrderCancelled
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order->loadMissing(['table.section']);
        SseEventService::dispatch('order.cancelled', [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'type'         => $order->type,
            'table_id'     => $order->table_id,
            'table_number' => $order->table?->number,
            'section_id'   => $order->table?->section_id,
            'cancelled_by' => $event->cancelledBy,
            'cancelled_at' => now()->toIso8601String(),
        ], $order->id);
    }
}

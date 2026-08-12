<?php
namespace App\Listeners;
use App\Events\OrderReady;
use App\Services\SseEventService;

class BroadcastOrderReady
{
    public function handle(OrderReady $event): void
    {
        $order = $event->order->loadMissing(['table.section']);
        SseEventService::dispatch('order.ready', [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'type'         => $order->type,
            'table_id'     => $order->table_id,
            'table_number' => $order->table?->number,
            'section_id'   => $order->table?->section_id,
            'section_name' => $order->table?->section?->name,
            'client_name'  => $order->client_name,
            'ready_at'     => now()->toIso8601String(),
        ], $order->id);
    }
}

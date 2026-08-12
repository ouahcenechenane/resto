<?php
namespace App\Listeners;
use App\Events\OrderCreated;
use App\Services\SseEventService;

class BroadcastOrderCreated
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order->loadMissing(['table.section', 'user:id,name']);
        SseEventService::dispatch('order.created', [
            'order_id'      => $order->id,
            'order_number'  => $order->order_number,
            'type'          => $order->type,
            'status'        => $order->status,
            'table_id'      => $order->table_id,
            'table_number'  => $order->table?->number,
            'section_id'    => $order->table?->section_id,
            'section_name'  => $order->table?->section?->name,
            'persons_count' => $order->persons_count,
            'created_by'    => $event->createdBy,
            'created_at'    => $order->created_at?->toIso8601String(),
        ], $order->id);
    }
}

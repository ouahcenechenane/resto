<?php
namespace App\Listeners;
use App\Events\OrderBilled;
use App\Services\SseEventService;

class BroadcastOrderBilled
{
    public function handle(OrderBilled $event): void
    {
        $order  = $event->order->loadMissing(['table.section']);
        $ticket = $event->ticket;
        SseEventService::dispatch('order.billed', [
            'order_id'      => $order->id,
            'order_number'  => $order->order_number,
            'type'          => $order->type,
            'table_id'      => $order->table_id,
            'table_number'  => $order->table?->number,
            'section_id'    => $order->table?->section_id,
            'ticket_id'     => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'total_amount'  => $ticket->total_amount,
            'billed_at'     => now()->toIso8601String(),
        ], $order->id);
    }
}

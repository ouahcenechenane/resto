<?php
namespace App\Listeners;
use App\Events\TicketPaid;
use App\Services\SseEventService;

class BroadcastTicketPaid
{
    public function handle(TicketPaid $event): void
    {
        $ticket = $event->ticket;
        $order  = $event->order->loadMissing(['table.section']);
        SseEventService::dispatch('ticket.paid', [
            'ticket_id'      => $ticket->id,
            'ticket_number'  => $ticket->ticket_number,
            'order_id'       => $order->id,
            'order_number'   => $order->order_number,
            'type'           => $order->type,
            'table_id'       => $order->table_id,
            'table_number'   => $order->table?->number,
            'section_id'     => $order->table?->section_id,
            'client_name'    => $order->client_name,
            'total_amount'   => $ticket->total_amount,
            'paid_amount'    => $ticket->paid_amount,
            'payment_method' => $ticket->payment_method,
            'change'         => $event->change,
            'paid_at'        => $ticket->paid_at?->toIso8601String(),
        ], $ticket->id);
    }
}

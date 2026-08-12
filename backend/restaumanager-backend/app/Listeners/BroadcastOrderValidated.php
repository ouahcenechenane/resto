<?php
namespace App\Listeners;
use App\Events\OrderValidated;
use App\Services\SseEventService;

class BroadcastOrderValidated
{
    public function handle(OrderValidated $event): void
    {
        $order = $event->order->loadMissing(['table.section', 'persons.items', 'user:id,name']);
        $items = [];
        foreach ($order->persons as $person) {
            foreach ($person->items as $item) {
                $items[] = [
                    'id'           => $item->id,
                    'person_index' => $person->person_index,
                    'person_label' => $person->label,
                    'menu_item_id' => $item->menu_item_id,
                    'name'         => $item->item_name,
                    'quantity'     => $item->quantity,
                    'kitchen_note' => $item->kitchen_note,
                    'is_free'      => $item->is_free,
                ];
            }
        }
        SseEventService::dispatch('order.validated', [
            'order_id'      => $order->id,
            'order_number'  => $order->order_number,
            'type'          => $order->type,
            'status'        => 'validated',
            'table_id'      => $order->table_id,
            'table_number'  => $order->table?->number,
            'section_id'    => $order->table?->section_id,
            'section_name'  => $order->table?->section?->name,
            'persons_count' => $order->persons_count,
            'total_amount'  => $order->total_amount,
            'items'         => $items,
            'auto'          => $event->auto,
            'validated_at'  => now()->toIso8601String(),
        ], $order->id);
    }
}

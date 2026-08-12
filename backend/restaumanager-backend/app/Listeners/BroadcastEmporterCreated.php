<?php
namespace App\Listeners;
use App\Events\EmporterCreated;
use App\Services\SseEventService;

class BroadcastEmporterCreated
{
    public function handle(EmporterCreated $event): void
    {
        $order = $event->order->loadMissing(['persons.items']);
        $items = [];
        foreach ($order->persons as $person) {
            foreach ($person->items as $item) {
                $items[] = [
                    'name'         => $item->item_name,
                    'quantity'     => $item->quantity,
                    'kitchen_note' => $item->kitchen_note,
                ];
            }
        }
        SseEventService::dispatch('emporter.created', [
            'order_id'      => $order->id,
            'order_number'  => $order->order_number,
            'client_name'   => $order->client_name,
            'persons_count' => $order->persons_count,
            'total_amount'  => $order->total_amount,
            'items'         => $items,
            'created_by'    => $event->createdBy,
            'created_at'    => $order->created_at?->toIso8601String(),
        ], $order->id);
    }
}

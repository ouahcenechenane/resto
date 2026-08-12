<?php
namespace App\Listeners;
use App\Events\OrderItemChanged;
use App\Services\SseEventService;

class BroadcastOrderItemChanged
{
    public function handle(OrderItemChanged $event): void
    {
        $order = $event->order->loadMissing(['table.section']);
        $item  = $event->item;

        $sseEventName = match ($event->action) {
            OrderItemChanged::ACTION_ADDED    => 'order.item_added',
            OrderItemChanged::ACTION_UPDATED  => 'order.item_updated',
            OrderItemChanged::ACTION_REMOVED  => 'order.item_removed',
            OrderItemChanged::ACTION_OFFERED  => 'order.item_offered',
            OrderItemChanged::ACTION_DISCOUNT => 'order.item_discounted',
            OrderItemChanged::ACTION_NOTE     => 'order.item_noted',
            default                           => 'order.item_changed',
        };

        SseEventService::dispatch($sseEventName, [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'type'         => $order->type,
            'table_id'     => $order->table_id,
            'table_number' => $order->table?->number,
            'section_id'   => $order->table?->section_id,
            'person_index' => $event->personIndex,
            'item_id'      => $item?->id,
            'item_name'    => $event->itemName,
            'quantity'     => $item?->quantity,
            'unit_price'   => $item?->unit_price,
            'discount_pct' => $item?->discount_percent,
            'is_free'      => $item?->is_free,
            'kitchen_note' => $item?->kitchen_note,
            'line_total'   => $item?->line_total,
            'order_total'  => $order->total_amount,
            'action'       => $event->action,
        ], $order->id);
    }
}

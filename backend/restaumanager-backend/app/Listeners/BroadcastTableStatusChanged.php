<?php
namespace App\Listeners;
use App\Events\TableStatusChanged;
use App\Services\SseEventService;

class BroadcastTableStatusChanged
{
    public function handle(TableStatusChanged $event): void
    {
        $table = $event->table->loadMissing('section');
        SseEventService::dispatch('table.status_changed', [
            'table_id'     => $table->id,
            'table_number' => $table->number,
            'section_id'   => $table->section_id,
            'section_name' => $table->section?->name,
            'capacity'     => $table->capacity,
            'old_status'   => $event->oldStatus,
            'new_status'   => $event->newStatus,
            'order_id'     => $event->orderId,
        ], $table->id);
    }
}

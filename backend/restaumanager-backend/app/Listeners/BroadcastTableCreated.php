<?php
namespace App\Listeners;
use App\Events\TableCreated;
use App\Services\SseEventService;

class BroadcastTableCreated
{
    public function handle(TableCreated $event): void
    {
        $table = $event->table->loadMissing('section');
        SseEventService::dispatch('table.created', [
            'table_id'     => $table->id,
            'table_number' => $table->number,
            'section_id'   => $table->section_id,
            'section_name' => $table->section?->name,
            'capacity'     => $table->capacity,
            'status'       => $table->status,
        ], $table->id);
    }
}

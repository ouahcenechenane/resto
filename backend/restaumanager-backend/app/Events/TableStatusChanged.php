<?php
namespace App\Events;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableStatusChanged
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly RestaurantTable $table,
        public readonly string          $oldStatus,
        public readonly string          $newStatus,
        public readonly ?int            $orderId = null,
    ) {}
}

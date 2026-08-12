<?php
namespace App\Events;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableCreated
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly RestaurantTable $table) {}
}

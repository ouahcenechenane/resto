<?php
namespace App\Events;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderBilled
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly Order  $order,
        public readonly Ticket $ticket,
    ) {}
}

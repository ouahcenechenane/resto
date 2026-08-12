<?php
namespace App\Events;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketPaid
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly Ticket $ticket,
        public readonly Order  $order,
        public readonly float  $change,
    ) {}
}

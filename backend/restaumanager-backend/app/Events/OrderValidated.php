<?php
namespace App\Events;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderValidated
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly Order $order,
        public readonly bool  $auto = false,
    ) {}
}

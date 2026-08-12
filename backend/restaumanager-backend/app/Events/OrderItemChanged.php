<?php
namespace App\Events;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderItemChanged
{
    use Dispatchable, SerializesModels;
    public const ACTION_ADDED    = 'added';
    public const ACTION_UPDATED  = 'updated';
    public const ACTION_REMOVED  = 'removed';
    public const ACTION_OFFERED  = 'offered';
    public const ACTION_DISCOUNT = 'discount';
    public const ACTION_NOTE     = 'note';

    public function __construct(
        public readonly Order      $order,
        public readonly ?OrderItem $item,
        public readonly string     $action,
        public readonly string     $itemName,
        public readonly int        $personIndex,
    ) {}
}

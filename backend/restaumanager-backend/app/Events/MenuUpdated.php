<?php
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuUpdated
{
    use Dispatchable, SerializesModels;
    public const ACTION_ITEM_CREATED = 'item_created';
    public const ACTION_ITEM_UPDATED = 'item_updated';
    public const ACTION_ITEM_TOGGLED = 'item_toggled';
    public const ACTION_ITEM_DELETED = 'item_deleted';
    public const ACTION_CAT_CREATED  = 'category_created';
    public const ACTION_CAT_UPDATED  = 'category_updated';
    public const ACTION_CAT_DELETED  = 'category_deleted';

    public function __construct(
        public readonly string  $action,
        public readonly int     $itemId,
        public readonly ?string $itemName,
        public readonly ?float  $newPrice    = null,
        public readonly ?bool   $isAvailable = null,
    ) {}
}

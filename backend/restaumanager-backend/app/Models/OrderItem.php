<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_person_id', 'menu_item_id', 'item_name',
        'unit_price', 'quantity', 'discount_percent',
        'is_free', 'free_reason',
        'is_returned', 'return_reason',
        'kitchen_note', 'line_total',
    ];
    protected $casts = [
        'unit_price'       => 'float',
        'discount_percent' => 'float',
        'line_total'       => 'float',
        'is_free'          => 'boolean',
        'is_returned'      => 'boolean',
    ];

    public function orderPerson() {
        return $this->belongsTo(OrderPerson::class, 'order_person_id');
    }
    public function menuItem() {
        return $this->belongsTo(MenuItem::class);
    }

    public function calculateLineTotal(): float
    {
        if ($this->is_free || $this->is_returned) return 0;
        return round($this->unit_price * (1 - $this->discount_percent / 100) * $this->quantity, 2);
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $item->line_total = $item->calculateLineTotal();
        });
    }
}

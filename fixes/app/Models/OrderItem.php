<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_person_id', 'menu_item_id', 'item_name',
        'unit_price', 'quantity', 'discount_percent', 'is_free', 'free_reason', 'line_total',
    ];

    protected $casts = [
        'unit_price'       => 'float',
        'discount_percent' => 'float',
        'line_total'       => 'float',
        'is_free'          => 'boolean',
    ];

    public function orderPerson()
    {
        return $this->belongsTo(OrderPerson::class, 'order_person_id');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    /** Calcule la valeur de la ligne */
    public function calculateLineTotal(): float
    {
        if ($this->is_free) return 0;
        $price = $this->unit_price * (1 - $this->discount_percent / 100);
        return round($price * $this->quantity, 2);
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $item->line_total = $item->calculateLineTotal();
        });
    }
}
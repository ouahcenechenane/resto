<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPerson extends Model
{
    protected $table    = 'order_persons';
    protected $fillable = ['order_id', 'person_index', 'label', 'subtotal'];
    protected $casts    = ['subtotal' => 'float'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_person_id');
    }

    /** Recalcule le sous-total de cette personne */
    public function recalculateSubtotal(): void
    {
        $sub = $this->items->sum('line_total');
        $this->update(['subtotal' => $sub]);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'order_id', 'printed_by', 'ticket_number', 'total_amount',
        'paid_amount', 'payment_method', 'status', 'snapshot', 'printed_at', 'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'paid_amount'  => 'float',
        'snapshot'     => 'array',
        'printed_at'   => 'datetime',
        'paid_at'      => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    /** Génère un numéro unique de ticket */
    public static function generateNumber(): string
    {
        $date  = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return sprintf('TICK-%s-%04d', $date, $count);
    }
}
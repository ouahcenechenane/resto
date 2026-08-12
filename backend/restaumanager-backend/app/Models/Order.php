<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'table_id', 'user_id', 'type', 'status',
        'persons_count', 'client_name', 'order_number',
        'total_amount', 'discount_amount', 'notes',
        'opened_at', 'closed_at',
    ];
    protected $casts = [
        'total_amount'    => 'float',
        'discount_amount' => 'float',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public function table() {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function persons() {
        return $this->hasMany(OrderPerson::class)->orderBy('person_index');
    }
    public function tickets() {
        return $this->hasMany(Ticket::class);
    }

    public function scopeActive($q) {
        return $q->whereIn('status', ['open', 'validated', 'billed']);
    }
    public function scopeSurPlace($q) {
        return $q->where('type', 'sur_place');
    }
    public function scopeEmporter($q) {
        return $q->where('type', 'emporter');
    }
}

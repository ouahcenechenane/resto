<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    protected $table    = 'tables';
    protected $fillable = ['section_id', 'number', 'capacity', 'status'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class, 'table_id')
                    ->whereIn('status', ['open', 'validated']);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
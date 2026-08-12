<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['code', 'name', 'icon', 'order', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function categories() {
        return $this->hasMany(Category::class)->orderBy('order');
    }
    public function tables() {
        return $this->hasMany(RestaurantTable::class);
    }
}

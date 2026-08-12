<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['section_id', 'name', 'icon', 'type', 'order', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function section() {
        return $this->belongsTo(Section::class);
    }
    public function items() {
        return $this->hasMany(MenuItem::class)->orderBy('order');
    }
}

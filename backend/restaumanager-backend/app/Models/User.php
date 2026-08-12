<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'username', 'password', 'role', 'is_active', 'permissions'];
    protected $hidden   = ['password', 'remember_token'];
    protected $casts    = [
        'is_active'   => 'boolean',
        'permissions' => 'array',
    ];

    public function orders()  { return $this->hasMany(Order::class); }
    public function tickets() { return $this->hasMany(Ticket::class, 'printed_by'); }
}
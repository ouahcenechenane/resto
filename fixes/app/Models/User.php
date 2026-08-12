<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'username', 'password', 'role', 'is_active', 'permissions'];
    protected $hidden   = ['password', 'remember_token'];
    protected $casts    = ['is_active' => 'boolean'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isCaissier(): bool { return in_array($this->role, ['caissier_restau', 'caissier_caffet']); }
    public function isServeur(): bool  { return in_array($this->role, ['serveur_restau', 'serveur_caffet']); }
    public function isAdmin(): bool    { return $this->role === 'admin'; }
}
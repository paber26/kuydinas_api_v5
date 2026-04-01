<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Kolom yang boleh diisi mass assignment
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'whatsapp',
        'coin_balance',
        'role',
        'is_active',
        'last_login',
        'device_login',
        'provider',
        'provider_id',
        'image',
        'province_code',
        'province_name',
        'regency_code',
        'regency_name',
        'district_code',
        'district_name',
    ];

    /**
     * Kolom yang disembunyikan dari response API
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting tipe data
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'coin_balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Helper cek apakah user admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function tryoutRegistrations()
    {
        return $this->hasMany(TryoutRegistration::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function topupTransactions()
    {
        return $this->hasMany(TopupTransaction::class);
    }
}

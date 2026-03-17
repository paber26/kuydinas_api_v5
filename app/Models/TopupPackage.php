<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopupPackage extends Model
{
    protected $fillable = [
        'name',
        'coin_amount',
        'bonus_coin',
        'price',
        'is_active',
    ];

    protected $casts = [
        'coin_amount' => 'integer',
        'bonus_coin' => 'integer',
        'price' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function topupTransactions()
    {
        return $this->hasMany(TopupTransaction::class);
    }
}

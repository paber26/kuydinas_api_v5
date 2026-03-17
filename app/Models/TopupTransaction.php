<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopupTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'topup_package_id',
        'order_id',
        'gateway',
        'gross_amount',
        'coin_amount',
        'bonus_coin',
        'status',
        'transaction_status',
        'fraud_status',
        'payment_type',
        'snap_token',
        'redirect_url',
        'raw_response',
        'raw_notification',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'topup_package_id' => 'integer',
        'coin_amount' => 'integer',
        'bonus_coin' => 'integer',
        'gross_amount' => 'integer',
        'raw_response' => 'array',
        'raw_notification' => 'array',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topupPackage()
    {
        return $this->belongsTo(TopupPackage::class);
    }
}

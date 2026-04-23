<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'bundle_id',
        'order_id',
        'gateway',
        'snap_token',
        'redirect_url',
        'gross_amount',
        'status',
        'transaction_status',
        'fraud_status',
        'payment_type',
        'raw_response',
        'raw_notification',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'bundle_id' => 'integer',
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

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'cover_image',
        'limit_type',
        'limit_quota',
        'limit_start_date',
        'limit_end_date',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'limit_quota' => 'integer',
        'limit_start_date' => 'date',
        'limit_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function tryouts()
    {
        return $this->belongsToMany(Tryout::class, 'bundle_tryout')->withTimestamps();
    }

    public function transactions()
    {
        return $this->hasMany(BundleTransaction::class);
    }

    /**
     * Jumlah transaksi yang sudah paid.
     */
    public function purchasedCount(): int
    {
        return $this->transactions()->where('status', 'paid')->count();
    }

    /**
     * Cek apakah bundle masih bisa dibeli.
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->limit_type === 'quota') {
            if ($this->limit_quota !== null && $this->purchasedCount() >= $this->limit_quota) {
                return false;
            }
        }

        if ($this->limit_type === 'time') {
            $now = now()->startOfDay();

            if ($this->limit_start_date && $now->lt($this->limit_start_date)) {
                return false;
            }

            if ($this->limit_end_date && $now->gt($this->limit_end_date)) {
                return false;
            }
        }

        return true;
    }
}

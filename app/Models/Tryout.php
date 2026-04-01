<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tryout extends Model
{
    protected $fillable = [
        'title',
        'duration',
        'status',
        'type',
        'quota',
        'price',
        'discount',
        'free_start_date',
        'free_valid_days',
        'free_valid_until',
        'info_ig',
        'info_wa',
        'twk_target',
        'tiu_target',
        'tkp_target',
        'twk_pg',
        'tiu_pg',
        'tkp_pg',
    ];

    protected $casts = [
        'free_start_date' => 'date',
        'free_valid_until' => 'date',
    ];

    public function soals()
    {
        return $this->belongsToMany(Soal::class, 'tryout_soal')
            ->withPivot('urutan_soal')
            ->withTimestamps()
            ->orderByPivot('urutan_soal', 'asc');
    }

    public function registrations()
    {
        return $this->hasMany(TryoutRegistration::class);
    }
}

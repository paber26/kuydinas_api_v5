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
        'twk_target',
        'tiu_target',
        'tkp_target',
        'twk_pg',
        'tiu_pg',
        'tkp_pg',
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

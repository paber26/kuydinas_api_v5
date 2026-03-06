<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soal extends Model
{
    protected $fillable = [
        'question',
        'category',
        'difficulty',
        'sub_category',
        'options',
        'correct_answer',
        'explanation',
        'status'
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function tryouts()
    {
        return $this->belongsToMany(Tryout::class, 'tryout_soal')
        ->withPivot('urutan_soal')
        ->withTimestamps();
    }
}
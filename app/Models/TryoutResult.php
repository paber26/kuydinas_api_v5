<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TryoutResult extends Model
{
    protected $fillable = [
        'user_id',
        'tryout_id',
        'score',
        'correct_answer',
        'answers',
        'session_state',
        'started_at'
    ];

    protected $casts = [
        'score' => 'integer',
        'correct_answer' => 'integer',
        'answers' => 'array',
        'session_state' => 'array',
        'started_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tryout()
    {
        return $this->belongsTo(Tryout::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TryoutRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'tryout_id',
        'status',
        'registered_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleTryoutSwap extends Model
{
    protected $fillable = [
        'user_id',
        'bundle_id',
        'original_tryout_id',
        'replacement_tryout_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function originalTryout()
    {
        return $this->belongsTo(Tryout::class, 'original_tryout_id');
    }

    public function replacementTryout()
    {
        return $this->belongsTo(Tryout::class, 'replacement_tryout_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity',
        'description',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
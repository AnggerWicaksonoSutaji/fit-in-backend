<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workouts',
        'calories',
        'streak',
        'today_sessions',
        'today_calories',
        'last_workout_date',
        'daily_history',
    ];

    protected $casts = [
        'daily_history' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

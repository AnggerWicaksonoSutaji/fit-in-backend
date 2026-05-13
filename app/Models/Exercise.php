<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = ['nama_latihan', 'otot', 'video_latihan'];

    public function workoutPlans()
    {
        return $this->hasMany(WorkoutPlan::class);
    }
}

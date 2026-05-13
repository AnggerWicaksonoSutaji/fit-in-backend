<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'user_id', 'jenis_program', 'tdee', 'target_kalori',
        'protein_g', 'karbo_g', 'lemak_g',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

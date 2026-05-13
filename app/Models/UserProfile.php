<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 'umur', 'jenis_kelamin', 'berat_badan',
        'tinggi_badan', 'tingkat_aktivitas', 'goal',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'paket_langganan_id', 'tgl_bayar',
        'status_bayar', 'status_langganan', 'expired_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paketLangganan()
    {
        return $this->belongsTo(PaketLangganan::class);
    }
}

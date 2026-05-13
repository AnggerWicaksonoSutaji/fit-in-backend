<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaketLangganan extends Model
{
    protected $fillable = ['nama_paket', 'harga', 'durasi_hari'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

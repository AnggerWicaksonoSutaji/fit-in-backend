<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\PaketLangganan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentController extends Controller
{
    // Daftar paket
    public function packages()
    {
        $pakets = PaketLangganan::all();

        // Jika belum ada paket, seed otomatis
        if ($pakets->isEmpty()) {
            PaketLangganan::insert([
                ['nama_paket' => 'Bulanan',   'harga' => 49000,  'durasi_hari' => 30,  'created_at' => now(), 'updated_at' => now()],
                ['nama_paket' => '3 Bulan',   'harga' => 119000, 'durasi_hari' => 90,  'created_at' => now(), 'updated_at' => now()],
                ['nama_paket' => '6 Bulan',   'harga' => 199000, 'durasi_hari' => 180, 'created_at' => now(), 'updated_at' => now()],
                ['nama_paket' => 'Tahunan',   'harga' => 349000, 'durasi_hari' => 365, 'created_at' => now(), 'updated_at' => now()],
            ]);
            $pakets = PaketLangganan::all();
        }

        return response()->json($pakets);
    }

    // Proses pembayaran (simulasi/dummy)
    public function checkout(Request $request)
    {
        $request->validate([
            'paket_id' => 'required|exists:paket_langganans,id',
        ]);

        $user  = Auth::user();
        $paket = PaketLangganan::find($request->paket_id);

        // Buat transaksi
        $transaction = Transaction::create([
            'user_id'            => $user->id,
            'paket_langganan_id' => $paket->id,
            'tgl_bayar'          => Carbon::today(),
            'status_bayar'       => 'paid',       // simulasi langsung paid
            'status_langganan'   => 'active',
            'expired_at'         => Carbon::today()->addDays($paket->durasi_hari),
        ]);

        // Update role user jadi premium (pakai User::find untuk memastikan save ke DB)
        \App\Models\User::where('id', $user->id)->update(['role' => 'premium']);

        return response()->json([
            'message'     => 'Pembayaran berhasil (simulasi)',
            'transaction' => $transaction,
            'user'        => \App\Models\User::find($user->id),
        ]);
    }

    // Cek status premium user
    public function status()
    {
        $user = Auth::user();
        $lastTransaction = Transaction::where('user_id', $user->id)
            ->where('status_langganan', 'active')
            ->latest()
            ->first();

        return response()->json([
            'is_premium'  => $user->role === 'premium',
            'transaction' => $lastTransaction,
        ]);
    }
}

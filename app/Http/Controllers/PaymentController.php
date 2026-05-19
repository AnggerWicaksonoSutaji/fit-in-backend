<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\PaketLangganan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Midtrans\Config;
use Midtrans\Snap;
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

    public function checkout(Request $request)
    {
        $request->validate([
            'paket_id' => 'required|exists:paket_langganans,id',
        ]);

        $user  = Auth::user();
        $paket = PaketLangganan::find($request->paket_id);

        // Buat transaksi dengan status pending
        $transaction = Transaction::create([
            'user_id'            => $user->id,
            'paket_langganan_id' => $paket->id,
            'tgl_bayar'          => Carbon::today(),
            'status_bayar'       => 'pending',    // Set ke pending
            'status_langganan'   => 'expired',   // Set ke expired sampai dibayar
            'expired_at'         => null,
        ]);
// Set your Merchant Server Key
        \Midtrans\Config::$serverKey = config("midtrans.serverkey");
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;
        // Bypass SSL certificate check on local environment
        \Midtrans\Config::$curlOptions = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['X-Midtrans-Bypass: true'] // Mencegah bug 'Undefined array key 10023' di SDK
        ];

        $params = [
            'transaction_details' => [
                'order_id' => 'FITIN-' . $transaction->id . '-' . time(),
                'gross_amount' => $paket->harga,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => $paket->id,
                    'price' => $paket->harga,
                    'quantity' => 1,
                    'name' => 'Paket Premium ' . $paket->nama_paket,
                ]
            ]
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'message'     => 'Token Midtrans berhasil di-generate',
                'snap_token'  => $snapToken,
                'transaction' => $transaction,
                'user'        => \App\Models\User::find($user->id),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Webhook Midtrans
    public function webhook(Request $request)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        
        try {
            $notification = new \Midtrans\Notification();
        } catch (\Exception $e) {
            // Jika testing manual tanpa API Midtrans yang asli, ini bisa error
            // Tapi dalam production/sandbox asli, ini akan berfungsi
            return response()->json(['message' => 'Invalid notification'], 400);
        }

        $transactionStatus = $notification->transaction_status;
        $orderId = $notification->order_id; // contoh: FITIN-5-168923010
        
        // Ekstrak ID transaksi kita
        $orderIdParts = explode('-', $orderId);
        $transactionId = $orderIdParts[1] ?? null;

        if (!$transactionId) {
            return response()->json(['message' => 'Invalid order ID'], 400);
        }

        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            // Pembayaran berhasil
            $transaction->update([
                'status_bayar'     => 'paid',
                'status_langganan' => 'active',
                'expired_at'       => Carbon::today()->addDays($transaction->paket->durasi_hari ?? 30),
            ]);

            // Update role user menjadi premium
            \App\Models\User::where('id', $transaction->user_id)->update(['role' => 'premium']);

        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            // Pembayaran gagal/kadaluarsa
            $transaction->update([
                'status_bayar'     => 'failed',
                'status_langganan' => 'expired',
            ]);
        } else if ($transactionStatus == 'pending') {
            // Pembayaran masih tertunda
            $transaction->update([
                'status_bayar'     => 'pending',
            ]);
        }

        return response()->json(['message' => 'Notification received and processed']);
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

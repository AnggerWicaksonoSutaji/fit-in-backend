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
    /**
     * PACKAGES — Ambil daftar paket langganan
     *
     * Dipanggil FE saat halaman Payment dibuka.
     * Jika tabel paket_langganans masih kosong (belum di-seed),
     * sistem otomatis mengisi 4 paket default.
     */
    public function packages()
    {
        $pakets = PaketLangganan::all();

        // Auto-seed: jika belum ada paket di database, isi otomatis
        if ($pakets->isEmpty()) {
            PaketLangganan::insert([
                ['nama_paket' => 'Bulanan',   'harga' => 49000,  'durasi_hari' => 30,  'created_at' => now(), 'updated_at' => now()],
                ['nama_paket' => '3 Bulan',   'harga' => 129000, 'durasi_hari' => 90,  'created_at' => now(), 'updated_at' => now()],
                ['nama_paket' => '6 Bulan',   'harga' => 199000, 'durasi_hari' => 180, 'created_at' => now(), 'updated_at' => now()],
                ['nama_paket' => 'Tahunan',   'harga' => 449000, 'durasi_hari' => 365, 'created_at' => now(), 'updated_at' => now()],
            ]);
            $pakets = PaketLangganan::all();
        }

        return response()->json($pakets);
    }

    /**
     * CHECKOUT — Buat transaksi dan minta token Snap ke Midtrans
     *
     * Alur yang terjadi:
     *   1. Validasi bahwa paket_id yang dikirim FE ada di database
     *   2. Buat record transaksi baru di tabel 'transactions' (status awal: pending)
     *   3. Konfigurasi library Midtrans dengan Server Key dari .env
     *   4. Kirim data transaksi ke server Midtrans, dapatkan snap_token
     *   5. Kembalikan snap_token ke FE — FE pakai ini untuk buka popup Midtrans Snap
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'paket_id' => 'required|exists:paket_langganans,id', // pastikan ID paket benar-benar ada di DB
        ]);

        $user  = Auth::user();
        $paket = PaketLangganan::find($request->paket_id);

        // Buat record transaksi dulu di database (status masih 'pending' karena belum dibayar)
        $transaction = Transaction::create([
            'user_id'            => $user->id,
            'paket_langganan_id' => $paket->id,
            'tgl_bayar'          => Carbon::today(),
            'status_bayar'       => 'pending',   // belum dibayar
            'status_langganan'   => 'expired',   // belum aktif sampai pembayaran sukses
            'expired_at'         => null,
        ]);

        // ─── Konfigurasi Library Midtrans ───────────────────────────────────────
        // Server Key diambil dari file .env (MIDTRANS_SERVER_KEY=...)
        // isProduction=false berarti kita pakai Sandbox (uang palsu untuk testing)
        \Midtrans\Config::$serverKey    = config("midtrans.serverkey");
        \Midtrans\Config::$isProduction = false;  // ganti true saat deploy ke produksi
        \Midtrans\Config::$isSanitized  = true;   // Midtrans akan bersihkan input berbahaya
        \Midtrans\Config::$is3ds        = true;   // aktifkan 3D Secure untuk kartu kredit

        // Opsi curl tambahan untuk lingkungan lokal (bypass SSL karena localhost tidak punya sertifikat)
        \Midtrans\Config::$curlOptions = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['X-Midtrans-Bypass: true']
        ];

        // ─── Data yang dikirim ke Midtrans ──────────────────────────────────────
        // Midtrans butuh: detail transaksi, detail customer, dan detail item yang dibeli
        $params = [
            'transaction_details' => [
                'order_id'     => 'FITIN-' . $transaction->id, // ID unik untuk order ini (format: FITIN-5)
                'gross_amount' => $paket->harga,               // jumlah yang harus dibayar (dalam rupiah)
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email'      => $user->email,
            ],
            'item_details' => [
                [
                    'id'       => $paket->id,
                    'price'    => $paket->harga,
                    'quantity' => 1,
                    'name'     => 'Paket Premium ' . $paket->nama_paket,
                ]
            ]
        ];

        try {
            // Kirim permintaan ke server Midtrans → dapatkan snap_token
            // snap_token ini bersifat sementara (~24 jam) dan hanya untuk transaksi ini
            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'message'     => 'Token Midtrans berhasil di-generate',
                'snap_token'  => $snapToken,    // dikirim ke FE untuk membuka popup Snap
                'transaction' => $transaction,  // data transaksi (FE butuh transaction->id untuk /payment/success)
                'user'        => \App\Models\User::find($user->id),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * WEBHOOK — Terima notifikasi otomatis dari server Midtrans
     *
     * Normalnya, setelah pembayaran berhasil, server Midtrans mengirim HTTP POST
     * ke endpoint ini secara otomatis (bukan dari user/browser, tapi dari server Midtrans).
     *
     * PENTING: Webhook hanya bekerja jika server kita punya URL publik yang bisa diakses internet.
     * Di localhost, webhook TIDAK bisa masuk — itulah mengapa ada endpoint /payment/success manual.
     *
     * Alur webhook:
     *   1. Midtrans kirim notifikasi berisi order_id dan transaction_status
     *   2. Kita ekstrak ID transaksi dari order_id (format: FITIN-5 → id=5)
     *   3. Update status transaksi dan role user sesuai status pembayaran
     */
    public function webhook(Request $request)
    {
        Config::$serverKey    = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);

        try {
            // Buat objek Notification dari data yang dikirim Midtrans
            $notification = new \Midtrans\Notification();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid notification'], 400);
        }

        $transactionStatus = $notification->transaction_status; // 'settlement', 'pending', 'cancel', dll
        $orderId           = $notification->order_id;           // contoh: "FITIN-5"

        // Ambil angka ID dari format "FITIN-5" → dapat "5"
        $orderIdParts  = explode('-', $orderId);
        $transactionId = $orderIdParts[1] ?? null;

        if (!$transactionId) {
            return response()->json(['message' => 'Invalid order ID'], 400);
        }

        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        // ─── Proses berdasarkan status dari Midtrans ────────────────────────────
        if ($transactionStatus == 'settlement' || $transactionStatus == 'capture') {
            // Pembayaran BERHASIL → aktifkan langganan dan upgrade role user ke 'premium'
            $transaction->update([
                'status_bayar'     => 'paid',
                'status_langganan' => 'active',
                'expired_at'       => Carbon::today()->addDays($transaction->paket->durasi_hari ?? 30),
            ]);
            \App\Models\User::where('id', $transaction->user_id)->update(['role' => 'premium']);

        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            // Pembayaran GAGAL/DITOLAK/KADALUARSA → tandai sebagai gagal
            $transaction->update([
                'status_bayar'     => 'failed',
                'status_langganan' => 'expired',
            ]);
        } else if ($transactionStatus == 'pending') {
            // Pembayaran MENUNGGU (misalnya transfer bank yang belum dikonfirmasi)
            $transaction->update([
                'status_bayar' => 'pending',
            ]);
        }

        return response()->json(['message' => 'Notification received and processed']);
    }

    /**
     * STATUS — Cek apakah user sudah premium
     *
     * Dipanggil FE setelah pembayaran untuk memverifikasi status.
     * Juga berfungsi sebagai polling: jika ada transaksi 'pending',
     * sistem akan aktif mengecek ke API Midtrans apakah sudah dibayar.
     *
     * Ini menggantikan kebutuhan webhook di lingkungan lokal.
     */
    public function status()
    {
        $user = Auth::user();

        // Cari transaksi yang masih 'pending' milik user ini
        $pendingTransaction = Transaction::where('user_id', $user->id)
            ->where('status_bayar', 'pending')
            ->latest()
            ->first();

        if ($pendingTransaction) {
            try {
                // Tanya langsung ke API Midtrans: "Apakah order ini sudah dibayar?"
                \Midtrans\Config::$serverKey    = config("midtrans.serverkey") ?? env('MIDTRANS_SERVER_KEY');
                \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
                $midtransStatus = \Midtrans\Transaction::status('FITIN-' . $pendingTransaction->id);

                $tStatus = $midtransStatus->transaction_status ?? null;
                if ($tStatus == 'settlement' || $tStatus == 'capture') {
                    // Sudah dibayar! Update database dan upgrade user ke premium
                    $pendingTransaction->update([
                        'status_bayar'     => 'paid',
                        'status_langganan' => 'active',
                        'expired_at'       => Carbon::today()->addDays($pendingTransaction->paketLangganan->durasi_hari ?? 30),
                    ]);
                    \App\Models\User::where('id', $pendingTransaction->user_id)->update(['role' => 'premium']);
                    $user->role = 'premium';
                } else if ($tStatus == 'cancel' || $tStatus == 'deny' || $tStatus == 'expire') {
                    $pendingTransaction->update([
                        'status_bayar'     => 'failed',
                        'status_langganan' => 'expired',
                    ]);
                }
            } catch (\Exception $e) {
                // Abaikan error (misalnya API Midtrans tidak bisa dihubungi)
            }
        }

        // Ambil transaksi aktif terakhir untuk ditampilkan di FE
        $lastTransaction = Transaction::where('user_id', $user->id)
            ->where('status_langganan', 'active')
            ->latest()
            ->first();

        return response()->json([
            'is_premium'  => $user->role === 'premium',
            'transaction' => $lastTransaction,
        ]);
    }

    /**
     * SUCCESS — Update status transaksi secara manual (khusus lokal/testing)
     *
     * Mengapa endpoint ini ada?
     * → Di production, Midtrans yang otomatis beritahu kita via webhook.
     * → Di localhost, webhook tidak bisa masuk karena tidak ada URL publik.
     * → Jadi setelah FE mendapat callback onSuccess dari Snap,
     *   FE langsung panggil endpoint ini untuk update status di backend.
     */
    public function success(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required'
        ]);

        $transaction = Transaction::find($request->transaction_id);
        if (!$transaction) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Pastikan hanya pemilik transaksi yang bisa update (keamanan)
        if ($transaction->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update transaksi: tandai sudah dibayar dan aktifkan langganan
        $transaction->update([
            'status_bayar'     => 'paid',
            'status_langganan' => 'active',
            'expired_at'       => Carbon::today()->addDays($transaction->paket->durasi_hari ?? 30),
        ]);

        // Upgrade role user di tabel users menjadi 'premium'
        \App\Models\User::where('id', $transaction->user_id)->update(['role' => 'premium']);

        return response()->json(['message' => 'Success updated']);
    }
}

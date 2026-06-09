<?php

namespace App\Http\Controllers;

use App\Models\UserActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * REGISTER — Mendaftarkan user baru
     *
     * Yang terjadi saat endpoint POST /api/register dipanggil:
     *   1. Validasi data yang dikirim (username harus unik, email harus valid, password min 6 karakter)
     *   2. Buat record user baru di tabel 'users' dengan role default 'free'
     *   3. Catat aktivitas 'register' di tabel 'user_activities' untuk log admin
     *   4. Buat token autentikasi (Sanctum) dan kembalikan ke FE
     */
    public function register(Request $request)
    {
        // Validasi: jika ada yang tidak sesuai, Laravel otomatis kembalikan error 422
        $request->validate([
            'username' => 'required|unique:users,name',    // username tidak boleh sudah ada di kolom 'name'
            'email'    => 'required|email|unique:users',   // email harus format yang benar dan unik
            'password' => 'required|min:6|confirmed',      // 'confirmed' berarti harus ada field 'password_confirmation'
        ]);

        // Simpan user baru ke database
        $user = User::create([
            'name'     => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password), // password di-hash, tidak disimpan plain text
            'role'     => 'free',                         // semua user baru mulai sebagai 'free'
        ]);

        // Catat aktivitas register ke tabel user_activities (untuk monitoring admin)
        UserActivity::create([
            'user_id'     => $user->id,
            'activity'    => 'register',
            'description' => 'User baru mendaftar',
            'ip_address'  => $request->ip(),
        ]);

        // Buat token Sanctum — ini yang akan dikirim ke FE dan disimpan di localStorage["fitinToken"]
        $token = $user->createToken('fitinToken')->plainTextToken;

        // Kembalikan respon JSON berisi token + data user
        return response()->json([
            'message' => 'Registrasi berhasil',
            'token'   => $token,
            'user'    => $user
        ], 201); // 201 = Created
    }

    /**
     * LOGIN — Masuk ke aplikasi
     *
     * Yang terjadi saat endpoint POST /api/login dipanggil:
     *   1. Validasi input (username/email + password)
     *   2. Cari user di database berdasarkan email ATAU username
     *   3. Cocokkan password dengan Hash::check (tidak bisa dibanding langsung karena di-hash)
     *   4. Buat token baru dan kembalikan ke FE
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Deteksi: apakah input berupa email atau username?
        // Jika mengandung '@' → anggap sebagai email, jika tidak → anggap username
        $loginField = filter_var($request->username, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'name';

        // Cari user di database berdasarkan email atau username
        $user = User::where($loginField, $request->username)->first();

        // Jika user tidak ditemukan ATAU password tidak cocok → tolak
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah'
            ], 401); // 401 = Unauthorized
        }

        // Buat token baru untuk sesi login ini
        $token = $user->createToken('fitinToken')->plainTextToken;

        // Catat aktivitas login ke tabel user_activities
        UserActivity::create([
            'user_id'     => $user->id,
            'activity'    => 'login',
            'description' => 'User login ke aplikasi',
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => $user
        ]);
    }

    /**
     * LOGOUT — Keluar dari aplikasi
     *
     * Menghapus SEMUA token milik user dari database.
     * Setelah ini, token lama di localStorage FE tidak bisa dipakai lagi.
     */
    public function logout(Request $request)
    {
        // Catat aktivitas logout sebelum token dihapus
        UserActivity::create([
            'user_id'     => $request->user()->id,
            'activity'    => 'logout',
            'description' => 'User logout',
            'ip_address'  => $request->ip(),
        ]);

        // Hapus semua token user dari tabel 'personal_access_tokens'
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }

    /**
     * USER — Ambil data user yang sedang login
     *
     * Dipanggil oleh FE saat buka aplikasi untuk memastikan token masih valid
     * dan mengambil data terbaru user (termasuk profil dan program aktif)
     */
    public function user(Request $request)
    {
        $user = $request->user();
        // 'load' = eager loading: ambil relasi 'profile' dan 'program' sekaligus dalam 1 query
        $user->load(['profile', 'program']);

        return response()->json($user);
    }

    /**
     * UPDATE USERNAME — Ganti nama pengguna
     *
     * Validasi memastikan username baru:
     *   - Tidak kosong dan minimal 3 karakter
     *   - Belum dipakai user lain (tapi boleh sama dengan nama user sendiri)
     */
    public function updateUsername(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                'min:3',
                // ignore($user->id) = izinkan user simpan nama yang sama dengan miliknya sendiri
                Rule::unique('users', 'name')->ignore($user->id),
            ],
        ], [
            'username.unique'   => 'Username sudah digunakan oleh pengguna lain.',
            'username.required' => 'Username tidak boleh kosong.',
            'username.min'      => 'Username minimal 3 karakter.',
            'username.max'      => 'Username maksimal 255 karakter.',
        ]);

        $user->name = $request->username;
        $user->save();

        // Log aktivitas (dibungkus try-catch agar jika gagal, perubahan nama tetap tersimpan)
        try {
            UserActivity::create([
                'user_id'     => $user->id,
                'activity'    => 'update_username',
                'description' => 'User mengubah username menjadi: ' . $request->username,
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Exception $e) {
            // Log gagal, tapi username tetap tersimpan — tidak perlu panik
        }

        return response()->json([
            'message' => 'Username berhasil diubah',
            'user'    => $user
        ]);
    }
}
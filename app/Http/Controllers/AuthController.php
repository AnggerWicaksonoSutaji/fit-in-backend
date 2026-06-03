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
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|unique:users,name',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'free',
        ]);

        // Simpan log activity register
        UserActivity::create([
            'user_id'     => $user->id,
            'activity'    => 'register',
            'description' => 'User baru mendaftar',
            'ip_address'  => $request->ip(),
        ]);

        $token = $user->createToken('fitinToken')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'token'   => $token,
            'user'    => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Coba login pakai email atau username
        $loginField = filter_var($request->username, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'name';

        $user = User::where($loginField, $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah'
            ], 401);
        }

        $token = $user->createToken('fitinToken')->plainTextToken;

        // Simpan log activity login
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

    public function logout(Request $request)
    {
        // Simpan log activity logout
        UserActivity::create([
            'user_id'     => $request->user()->id,
            'activity'    => 'logout',
            'description' => 'User logout',
            'ip_address'  => $request->ip(),
        ]);

        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $user->load(['profile', 'program']);

        return response()->json($user);
    }

    public function updateUsername(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                'min:3',
                // Exclude current user so they can "re-save" their own name
                Rule::unique('users', 'name')->ignore($user->id),
            ],
        ], [
            'username.unique' => 'Username sudah digunakan oleh pengguna lain.',
            'username.required' => 'Username tidak boleh kosong.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 255 karakter.',
        ]);

        $user->name = $request->username;
        $user->save();

        // Log activity (non-fatal if it fails)
        try {
            UserActivity::create([
                'user_id'     => $user->id,
                'activity'    => 'update_username',
                'description' => 'User mengubah username menjadi: ' . $request->username,
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Exception $e) {
            // Log gagal, tapi username tetap tersimpan
        }

        return response()->json([
            'message' => 'Username berhasil diubah',
            'user'    => $user
        ]);
    }
}
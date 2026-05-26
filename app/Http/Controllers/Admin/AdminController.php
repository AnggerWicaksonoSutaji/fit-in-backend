<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkoutPlan;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'total_users' => User::count(),

            'premium_users' => User::where('role', 'premium')->count(),

            'workout_programs' => WorkoutPlan::count(),
        ]);
    }

    public function users()
    {
        return response()->json(
            User::latest()->get()
        );
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus'
        ]);
    }

    public function makeAdmin($id)
    {
        $user = User::findOrFail($id);

        $user->role = 'admin';

        $user->save();

        return response()->json([
            'message' => 'User berhasil dijadikan admin'
        ]);
    }
}
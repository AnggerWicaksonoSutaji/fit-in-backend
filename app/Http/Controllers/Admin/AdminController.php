<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\Exercise;
use App\Models\Transaction;

class AdminController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'total_users'          => User::count(),
            'premium_users'        => User::where('role', 'premium')->count(),
            'free_users'           => User::where('role', 'free')->count(),
            'admin_users'          => User::where('role', 'admin')->count(),
            'workout_programs'     => Exercise::count(),
            'total_workouts_done'  => WorkoutPlan::where('is_done', true)->count(),
            'total_transactions'   => Transaction::where('status_bayar', 'paid')->count(),
        ]);
    }

    public function users()
    {
        return response()->json(
            User::latest()->get([
                'id',
                'name',
                'email',
                'role',
                'created_at'
            ])
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
            'message' => 'User dijadikan admin',
            'user'    => $user
        ]);
    }

    public function makePremium($id)
    {
        $user = User::findOrFail($id);

        $user->role = 'premium';
        $user->save();

        return response()->json([
            'message' => 'User dijadikan premium',
            'user'    => $user
        ]);
    }

    public function makeFree($id)
    {
        $user = User::findOrFail($id);

        $user->role = 'free';
        $user->save();

        return response()->json([
            'message' => 'User dijadikan free',
            'user'    => $user
        ]);
    }
}
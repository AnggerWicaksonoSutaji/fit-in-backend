<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkoutHistory; // Assuming this exists or using something else
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $totalUsers = User::count();
        $premiumUsers = User::where('role', 'premium')->count();
        
        // Count total workout sessions or workout programs (if we have a Workout program table, but WorkoutController indicates we just have history maybe)
        // Since we don't know the exact schema, let's just make it robust.
        // I will check the database schema.
        
        return response()->json([
            'total_users' => $totalUsers,
            'workout_programs' => 0, // Placeholder for now
            'premium_users' => $premiumUsers,
        ]);
    }

    public function users(Request $request)
    {
        $users = User::select('id', 'name', 'email', 'role')->get();
        return response()->json($users);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActivityController extends Controller
{
    // GET semua aktivitas user (dengan field flat: user_name, user_email, user_role)
    public function index(Request $request)
    {
        $activities = UserActivity::with('user:id,name,email,role')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($act) {
                return [
                    'id'          => $act->id,
                    'activity'    => $act->activity,
                    'description' => $act->description,
                    'ip_address'  => $act->ip_address,
                    'time'        => $act->created_at
                        ? $act->created_at->diffForHumans()
                        : '-',
                    'user_name'   => $act->user->name  ?? 'Unknown',
                    'user_email'  => $act->user->email ?? '-',
                    'user_role'   => $act->user->role  ?? 'free',
                ];
            });

        return response()->json($activities);
    }

    // GET statistik aktivitas (today_logins, today_workouts, new_registers, recent_activities, total_activities)
    public function stats(Request $request)
    {
        $today = Carbon::today();

        $todayLogins    = UserActivity::where('activity', 'login')
                            ->whereDate('created_at', $today)->count();
        $todayWorkouts  = UserActivity::where('activity', 'workout_done')
                            ->whereDate('created_at', $today)->count();
        $newRegisters   = UserActivity::where('activity', 'register')
                            ->whereDate('created_at', $today)->count();
        $totalActivities = UserActivity::count();

        // 10 aktivitas terbaru untuk dashboard
        $recentActivities = UserActivity::with('user:id,name,role')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($act) {
                return [
                    'user'     => $act->user->name ?? 'Unknown',
                    'role'     => $act->user->role ?? 'free',
                    'activity' => $act->activity,
                    'time'     => $act->created_at
                        ? $act->created_at->diffForHumans()
                        : '-',
                ];
            });

        return response()->json([
            'today_logins'       => $todayLogins,
            'today_workouts'     => $todayWorkouts,
            'new_registers'      => $newRegisters,
            'total_activities'   => $totalActivities,
            'recent_activities'  => $recentActivities,
        ]);
    }
}
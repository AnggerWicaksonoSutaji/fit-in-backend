<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    // GET semua aktivitas user
    public function index(Request $request)
    {
        $activities = UserActivity::with('user:id,name,email')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($activities);
    }

    // GET statistik aktivitas
    public function stats(Request $request)
    {
        $totalLogins   = UserActivity::where('activity', 'login')->count();
        $totalLogouts  = UserActivity::where('activity', 'logout')->count();
        $totalRegisters = UserActivity::where('activity', 'register')->count();

        return response()->json([
            'total_logins'    => $totalLogins,
            'total_logouts'   => $totalLogouts,
            'total_registers' => $totalRegisters,
        ]);
    }
}
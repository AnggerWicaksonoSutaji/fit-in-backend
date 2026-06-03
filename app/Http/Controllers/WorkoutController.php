<?php

namespace App\Http\Controllers;

use App\Models\UserActivity;
use App\Models\WorkoutPlan;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkoutController extends Controller
{
    // Ambil jadwal workout user
    public function index()
    {
        $plans = WorkoutPlan::where('user_id', Auth::id())
            ->with('exercise')
            ->get();

        return response()->json($plans);
    }

    // Generate jadwal workout berdasarkan goal
    public function generate(Request $request)
    {
        $user = Auth::user();

        // Hapus jadwal lama
        WorkoutPlan::where('user_id', $user->id)->delete();

        // Seed exercises jika kosong
        if (Exercise::count() === 0) {
            Exercise::insert([
                ['nama_latihan' => 'Bench Press',       'otot' => 'Dada',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Push Up',           'otot' => 'Dada',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Shoulder Press',    'otot' => 'Bahu',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Lateral Raise',     'otot' => 'Bahu',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Squat',             'otot' => 'Paha',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Leg Press',         'otot' => 'Kaki',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Bicep Curl',        'otot' => 'Lengan',   'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Tricep Extension',  'otot' => 'Lengan',   'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Deadlift',          'otot' => 'Punggung', 'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Barbell Row',       'otot' => 'Punggung', 'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Pull Up',           'otot' => 'Punggung', 'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Plank',             'otot' => 'Perut',    'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Russian Twist',     'otot' => 'Perut',    'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
                ['nama_latihan' => 'Calf Raise',        'otot' => 'Kaki',     'video_latihan' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $exercises = Exercise::all();
        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        // Distribusi latihan per hari (skip Minggu = rest)
        foreach ($days as $i => $day) {

            if ($day === 'Minggu') {
                continue; // Rest day
            }

            // Ambil 2-3 exercise random untuk setiap hari
            $dailyExercises = $exercises->random(min(3, $exercises->count()));

            foreach ($dailyExercises as $exercise) {

                WorkoutPlan::create([
                    'user_id'      => $user->id,
                    'exercise_id'  => $exercise->id,
                    'hari_latihan' => $day,
                    'is_done'      => false,
                ]);
            }
        }

        $plans = WorkoutPlan::where('user_id', $user->id)
            ->with('exercise')
            ->get();

        return response()->json([
            'message' => 'Jadwal workout berhasil digenerate',
            'plans'   => $plans,
        ]);
    }

    // Tandai workout selesai
    public function markDone(Request $request, $id)
    {
        $plan = WorkoutPlan::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $plan->update([
            'is_done' => true
        ]);

        // Simpan log activity workout selesai
        UserActivity::create([
            'user_id'     => $request->user()->id,
            'activity'    => 'workout_done',
            'description' => 'Menyelesaikan workout',
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Workout ditandai selesai',
            'plan'    => $plan,
        ]);
    }

    // Fetch user stats from database
    public function getStats()
    {
        $userId = Auth::id();
        $stat = \App\Models\UserStat::firstOrCreate(
            ['user_id' => $userId],
            [
                'workouts' => 0,
                'calories' => 0,
                'streak' => 0,
                'today_sessions' => 0,
                'today_calories' => 0,
                'daily_history' => []
            ]
        );

        return response()->json($stat);
    }

    // Sync user stats from frontend
    public function updateStats(Request $request)
    {
        $userId = Auth::id();
        
        $request->validate([
            'workouts' => 'required|integer',
            'calories' => 'required|integer',
            'streak' => 'required|integer',
            'today_sessions' => 'required|integer',
            'today_calories' => 'required|integer',
            'last_workout_date' => 'nullable|date',
            'daily_history' => 'nullable|array'
        ]);

        $stat = \App\Models\UserStat::updateOrCreate(
            ['user_id' => $userId],
            [
                'workouts' => $request->workouts,
                'calories' => $request->calories,
                'streak' => $request->streak,
                'today_sessions' => $request->today_sessions,
                'today_calories' => $request->today_calories,
                'last_workout_date' => $request->last_workout_date,
                'daily_history' => $request->daily_history ?? []
            ]
        );

        return response()->json([
            'message' => 'Stats synced successfully',
            'stat' => $stat
        ]);
    }
}
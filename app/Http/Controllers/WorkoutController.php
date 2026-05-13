<?php

namespace App\Http\Controllers;

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
            if ($day === 'Minggu') continue; // Rest day

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
    public function markDone($id)
    {
        $plan = WorkoutPlan::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $plan->update(['is_done' => true]);

        return response()->json([
            'message' => 'Workout ditandai selesai',
            'plan'    => $plan,
        ]);
    }

    // Stats: hitung workout selesai, kalori, dll
    public function stats()
    {
        $userId = Auth::id();
        $total    = WorkoutPlan::where('user_id', $userId)->count();
        $done     = WorkoutPlan::where('user_id', $userId)->where('is_done', true)->count();
        $calories = $done * 200; // estimasi 200 kcal per workout
        $goalPct  = $total > 0 ? round(($done / $total) * 100) : 0;

        return response()->json([
            'workouts' => $done,
            'calories' => $calories,
            'streak'   => $done, // simplified streak
            'goalPct'  => $goalPct,
        ]);
    }
}

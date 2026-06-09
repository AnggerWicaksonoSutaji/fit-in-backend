<?php

namespace App\Http\Controllers;

use App\Models\UserActivity;
use App\Models\WorkoutPlan;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkoutController extends Controller
{
    /**
     * INDEX — Ambil semua rencana workout milik user yang sedang login
     *
     * Dipanggil FE untuk menampilkan jadwal workout yang sudah di-generate.
     * 'with('exercise')' = ambil data gerakan (nama, otot) sekaligus dalam 1 query (eager loading)
     */
    public function index()
    {
        $plans = WorkoutPlan::where('user_id', Auth::id())
            ->with('exercise') // join ke tabel 'exercises' untuk dapat detail gerakan
            ->get();

        return response()->json($plans);
    }

    /**
     * GENERATE — Buat jadwal workout otomatis berdasarkan goal user
     *
     * Alur:
     *   1. Hapus semua jadwal lama milik user ini
     *   2. Pastikan tabel exercises sudah terisi (auto-seed jika kosong)
     *   3. Distribusi 3 gerakan random ke setiap hari (kecuali Minggu = rest day)
     *   4. Simpan semua ke tabel workout_plans
     */
    public function generate(Request $request)
    {
        $user = Auth::user();

        // Hapus jadwal lama agar tidak menumpuk
        WorkoutPlan::where('user_id', $user->id)->delete();

        // Auto-seed tabel exercises jika masih kosong
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

        // Buat jadwal untuk tiap hari kecuali Minggu (rest day)
        foreach ($days as $i => $day) {
            if ($day === 'Minggu') {
                continue; // Minggu = rest day, tidak ada workout
            }

            // Ambil 3 gerakan secara acak dari semua gerakan yang ada
            $dailyExercises = $exercises->random(min(3, $exercises->count()));

            // Simpan setiap gerakan ke tabel workout_plans
            foreach ($dailyExercises as $exercise) {
                WorkoutPlan::create([
                    'user_id'      => $user->id,
                    'exercise_id'  => $exercise->id,
                    'hari_latihan' => $day,
                    'is_done'      => false, // belum selesai
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

    /**
     * MARK DONE — Tandai satu gerakan sebagai sudah selesai
     *
     * FE mengirim ID workout_plan yang ingin ditandai selesai.
     * 'firstOrFail' = jika tidak ditemukan, otomatis kembalikan error 404
     */
    public function markDone(Request $request, $id)
    {
        // Pastikan workout plan ini milik user yang login (bukan milik orang lain)
        $plan = WorkoutPlan::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $plan->update(['is_done' => true]);

        // Catat ke log aktivitas untuk monitoring admin
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

    /**
     * GET STATS — Ambil statistik workout user dari database
     *
     * 'firstOrCreate' = ambil data jika sudah ada, atau buat baru jika belum ada.
     * Ini memastikan setiap user selalu punya record di tabel user_stats.
     */
    public function getStats()
    {
        $userId = Auth::id();
        $stat   = \App\Models\UserStat::firstOrCreate(
            ['user_id' => $userId],  // cari berdasarkan user_id
            [                        // nilai default jika belum ada
                'workouts'       => 0,
                'calories'       => 0,
                'streak'         => 0,
                'today_sessions' => 0,
                'today_calories' => 0,
                'daily_history'  => []
            ]
        );

        return response()->json($stat);
    }

    /**
     * UPDATE STATS — Sinkronisasi statistik dari FE ke database
     *
     * Dipanggil setiap kali user selesai sesi workout.
     * FE mengirim data statistik terbaru → disimpan ke tabel user_stats.
     *
     * 'updateOrCreate' = update jika sudah ada, buat baru jika belum ada.
     * Ini memastikan tidak ada duplikasi data.
     */
    public function updateStats(Request $request)
    {
        $userId = Auth::id();

        // Validasi: semua field wajib ada dan bertipe data yang benar
        $request->validate([
            'workouts'          => 'required|integer',
            'calories'          => 'required|integer',
            'streak'            => 'required|integer',
            'today_sessions'    => 'required|integer',
            'today_calories'    => 'required|integer',
            'last_workout_date' => 'nullable|date',
            'daily_history'     => 'nullable|array'  // JSON array riwayat 90 hari
        ]);

        // Simpan/update statistik ke database
        $stat = \App\Models\UserStat::updateOrCreate(
            ['user_id' => $userId],
            [
                'workouts'          => $request->workouts,
                'calories'          => $request->calories,
                'streak'            => $request->streak,
                'today_sessions'    => $request->today_sessions,
                'today_calories'    => $request->today_calories,
                'last_workout_date' => $request->last_workout_date,
                'daily_history'     => $request->daily_history ?? []
            ]
        );

        return response()->json([
            'message' => 'Stats synced successfully',
            'stat'    => $stat
        ]);
    }
}
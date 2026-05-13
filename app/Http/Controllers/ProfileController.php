<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    // Simpan/update data diri user + hitung TDEE
    public function store(Request $request)
    {
        $request->validate([
            'umur'              => 'required|integer|min:10|max:100',
            'jenis_kelamin'     => 'required|in:Laki-laki,Perempuan',
            'berat_badan'       => 'required|numeric|min:20|max:300',
            'tinggi_badan'      => 'required|numeric|min:100|max:250',
            'tingkat_aktivitas' => 'required|in:jarang,sedang,sering',
            'goal'              => 'required|in:cutting,maintenance,bulking',
        ]);

        $user = Auth::user();

        // Simpan / update profil
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['umur', 'jenis_kelamin', 'berat_badan', 'tinggi_badan', 'tingkat_aktivitas', 'goal'])
        );

        // Hitung BMR (Mifflin-St Jeor)
        $w = $request->berat_badan;
        $h = $request->tinggi_badan;
        $a = $request->umur;

        if ($request->jenis_kelamin === 'Laki-laki') {
            $bmr = 10 * $w + 6.25 * $h - 5 * $a + 5;
        } else {
            $bmr = 10 * $w + 6.25 * $h - 5 * $a - 161;
        }

        // Faktor aktivitas
        $actMultiplier = ['jarang' => 1.2, 'sedang' => 1.55, 'sering' => 1.725];
        $tdee = round($bmr * ($actMultiplier[$request->tingkat_aktivitas] ?? 1.55));

        // Target kalori berdasarkan goal
        $targetKalori = $tdee;
        if ($request->goal === 'cutting') $targetKalori = round($tdee * 0.8);
        if ($request->goal === 'bulking') $targetKalori = round($tdee * 1.15);

        // Makronutrisi
        $protein = round(($targetKalori * 0.3) / 4);
        $karbo   = round(($targetKalori * 0.45) / 4);
        $lemak   = round(($targetKalori * 0.25) / 9);

        // Map goal ke jenis_program
        $jenisProgram = ucfirst($request->goal); // cutting -> Cutting

        // Simpan program
        $program = Program::updateOrCreate(
            ['user_id' => $user->id],
            [
                'jenis_program' => $jenisProgram,
                'tdee'          => $tdee,
                'target_kalori' => $targetKalori,
                'protein_g'     => $protein,
                'karbo_g'       => $karbo,
                'lemak_g'       => $lemak,
            ]
        );

        return response()->json([
            'message' => 'Profil dan program berhasil disimpan',
            'profile' => $profile,
            'program' => $program,
        ]);
    }

    // Ambil data profil & program user
    public function show()
    {
        $user = Auth::user();
        $profile = UserProfile::where('user_id', $user->id)->first();
        $program = Program::where('user_id', $user->id)->first();

        return response()->json([
            'profile' => $profile,
            'program' => $program,
        ]);
    }
}

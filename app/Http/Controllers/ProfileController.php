<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * STORE — Simpan data diri user dan hitung kebutuhan nutrisi otomatis
     *
     * Dipanggil saat user mengisi atau memperbarui form Data Diri.
     * Selain menyimpan data fisik, fungsi ini juga:
     *   1. Menghitung BMR (kalori dasar saat tubuh istirahat)
     *   2. Menghitung TDEE (total kebutuhan kalori per hari)
     *   3. Menyesuaikan target kalori sesuai goal (cutting/maintenance/bulking)
     *   4. Membagi target kalori ke 3 makronutrisi: Protein, Karbo, Lemak
     *   5. Menyimpan semua hasil ke tabel 'programs'
     */
    public function store(Request $request)
    {
        // Validasi: pastikan data yang dikirim FE sesuai format yang diharapkan
        $request->validate([
            'umur'              => 'required|integer|min:10|max:100',
            'jenis_kelamin'     => 'required|in:Laki-laki,Perempuan',
            'berat_badan'       => 'required|numeric|min:20|max:300',
            'tinggi_badan'      => 'required|numeric|min:100|max:250',
            'tingkat_aktivitas' => 'required|in:jarang,sedang,sering',
            'goal'              => 'required|in:cutting,maintenance,bulking',
        ]);

        $user = Auth::user();

        // Simpan atau update profil fisik di tabel 'user_profiles'
        // 'updateOrCreate' = jika sudah ada profil untuk user ini, update. Jika belum, buat baru.
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['umur', 'jenis_kelamin', 'berat_badan', 'tinggi_badan', 'tingkat_aktivitas', 'goal'])
        );

        // ─── HITUNG BMR (Basal Metabolic Rate) ──────────────────────────────────
        // BMR = kalori yang dibakar tubuh saat istirahat total (tidur seharian)
        // Menggunakan rumus Mifflin-St Jeor yang lebih akurat dari Harris-Benedict
        $w = $request->berat_badan;  // berat dalam kg
        $h = $request->tinggi_badan; // tinggi dalam cm
        $a = $request->umur;         // umur dalam tahun

        if ($request->jenis_kelamin === 'Laki-laki') {
            // Rumus laki-laki: (10 × berat) + (6.25 × tinggi) − (5 × umur) + 5
            $bmr = 10 * $w + 6.25 * $h - 5 * $a + 5;
        } else {
            // Rumus perempuan: (10 × berat) + (6.25 × tinggi) − (5 × umur) − 161
            $bmr = 10 * $w + 6.25 * $h - 5 * $a - 161;
        }

        // ─── HITUNG TDEE (Total Daily Energy Expenditure) ───────────────────────
        // TDEE = BMR × faktor aktivitas
        // Semakin aktif seseorang, semakin banyak kalori yang dibakar per hari
        $actMultiplier = [
            'jarang' => 1.2,   // hampir tidak pernah olahraga
            'sedang' => 1.55,  // olahraga 3-5x per minggu
            'sering' => 1.725, // olahraga hampir setiap hari
        ];
        $tdee = round($bmr * ($actMultiplier[$request->tingkat_aktivitas] ?? 1.55));

        // ─── SESUAIKAN TARGET KALORI BERDASARKAN GOAL ───────────────────────────
        $targetKalori = $tdee;
        if ($request->goal === 'cutting') {
            $targetKalori = round($tdee * 0.8);   // defisit 20% = turunkan berat badan
        }
        if ($request->goal === 'bulking') {
            $targetKalori = round($tdee * 1.15);  // surplus 15% = naikkan berat badan
        }
        // maintenance: target = TDEE (tidak berubah)

        // ─── HITUNG MAKRONUTRISI ────────────────────────────────────────────────
        // Pembagian: Protein 30%, Karbo 45%, Lemak 25% dari total kalori
        // Catatan: 1g protein = 4 kcal, 1g karbo = 4 kcal, 1g lemak = 9 kcal
        $protein = round(($targetKalori * 0.3) / 4);  // gram protein per hari
        $karbo   = round(($targetKalori * 0.45) / 4); // gram karbohidrat per hari
        $lemak   = round(($targetKalori * 0.25) / 9); // gram lemak per hari

        // Simpan atau update hasil kalkulasi ke tabel 'programs'
        $program = Program::updateOrCreate(
            ['user_id' => $user->id],
            [
                'jenis_program' => ucfirst($request->goal), // "cutting" → "Cutting"
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

    /**
     * SHOW — Ambil data profil dan program user yang sedang login
     *
     * Dipanggil FE saat membuka halaman Profil atau Nutrisi
     * untuk menampilkan data yang sudah tersimpan.
     */
    public function show()
    {
        $user    = Auth::user();
        $profile = UserProfile::where('user_id', $user->id)->first(); // data fisik
        $program = Program::where('user_id', $user->id)->first();     // hasil kalkulasi nutrisi

        return response()->json([
            'profile' => $profile,
            'program' => $program,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\Request;

class WorkoutManagementController extends Controller
{
    // GET semua exercise
    public function index()
    {
        $exercises = Exercise::latest()->get();
        return response()->json($exercises);
    }

    // POST tambah exercise baru
    public function store(Request $request)
    {
        $request->validate([
            'nama_latihan' => 'required|string|max:255',
            'otot'         => 'required|in:Dada,Bahu,Paha,Lengan,Punggung,Perut,Kaki',
            'video_latihan'=> 'nullable|string',
        ]);

        $exercise = Exercise::create([
            'nama_latihan'  => $request->nama_latihan,
            'otot'          => $request->otot,
            'video_latihan' => $request->video_latihan,
        ]);

        return response()->json([
            'message'  => 'Exercise berhasil ditambahkan',
            'exercise' => $exercise,
        ], 201);
    }

    // PUT update exercise
    public function update(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        $request->validate([
            'nama_latihan' => 'required|string|max:255',
            'otot'         => 'required|in:Dada,Bahu,Paha,Lengan,Punggung,Perut,Kaki',
            'video_latihan'=> 'nullable|string',
        ]);

        $exercise->update([
            'nama_latihan'  => $request->nama_latihan,
            'otot'          => $request->otot,
            'video_latihan' => $request->video_latihan,
        ]);

        return response()->json([
            'message'  => 'Exercise berhasil diupdate',
            'exercise' => $exercise,
        ]);
    }

    // DELETE exercise
    public function destroy($id)
    {
        $exercise = Exercise::findOrFail($id);
        $exercise->delete();

        return response()->json([
            'message' => 'Exercise berhasil dihapus',
        ]);
    }
}
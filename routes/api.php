<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Handle OPTIONS preflight
Route::options('/{any}', function() {
    return response()->json([], 200);
})->where('any', '.*');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Midtrans Webhook (harus public agar bisa diakses oleh server Midtrans)
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// Protected routes (memerlukan token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'user']);

    // Profile & Nutrisi
    Route::post('/profile',  [ProfileController::class, 'store']);
    Route::get('/profile',   [ProfileController::class, 'show']);

    // Payment
    Route::get('/packages',         [PaymentController::class, 'packages']);
    Route::post('/payment/checkout', [PaymentController::class, 'checkout']);
    Route::get('/payment/status',    [PaymentController::class, 'status']);

    // Workout
    Route::get('/workouts',          [WorkoutController::class, 'index']);
    Route::post('/workouts/generate', [WorkoutController::class, 'generate']);
    Route::patch('/workouts/{id}/done', [WorkoutController::class, 'markDone']);
    Route::get('/workouts/stats',    [WorkoutController::class, 'stats']);
});
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| OPTIONS PREFLIGHT
|--------------------------------------------------------------------------
*/
Route::options('/{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| MIDTRANS WEBHOOK
|--------------------------------------------------------------------------
*/
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (sementara tanpa auth - nanti aktifkan middleware admin)
|--------------------------------------------------------------------------
| Untuk production, ganti group ini menjadi:
| Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(...)
*/
Route::prefix('admin')->group(function () {

    // ── Dashboard ──
    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    // ── User Management ──
    Route::get('/users',                        [AdminController::class, 'users']);
    Route::patch('/users/{id}/make-admin',      [AdminController::class, 'makeAdmin']);
    Route::patch('/users/{id}/make-premium',    [AdminController::class, 'makePremium']);
    Route::patch('/users/{id}/make-free',       [AdminController::class, 'makeFree']);
    Route::delete('/users/{id}',                [AdminController::class, 'deleteUser']);

    // ── Workout Management ──
    Route::get('/workouts',         [WorkoutManagementController::class, 'index']);
    Route::post('/workouts',        [WorkoutManagementController::class, 'store']);
    Route::put('/workouts/{id}',    [WorkoutManagementController::class, 'update']);
    Route::delete('/workouts/{id}', [WorkoutManagementController::class, 'destroy']);

    // ── Activity Monitoring ──
    Route::get('/activities',       [ActivityController::class, 'index']);
    Route::get('/activities/stats', [ActivityController::class, 'stats']);

});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'user']);

    Route::post('/profile', [ProfileController::class, 'store']);
    Route::get('/profile',  [ProfileController::class, 'show']);

    Route::get('/packages',          [PaymentController::class, 'packages']);
    Route::post('/payment/checkout', [PaymentController::class, 'checkout']);
    Route::post('/payment/success',  [PaymentController::class, 'success']);
    Route::get('/payment/status',    [PaymentController::class, 'status']);

    // Workout
    Route::get('/workouts',          [WorkoutController::class, 'index']);
    Route::post('/workouts/generate', [WorkoutController::class, 'generate']);
    Route::patch('/workouts/{id}/done', [WorkoutController::class, 'markDone']);
    Route::get('/workouts/stats',    [WorkoutController::class, 'stats']);

    // Admin
    Route::get('/admin/dashboard',   [AdminController::class, 'dashboard']);
    Route::get('/admin/users',       [AdminController::class, 'users']);
});
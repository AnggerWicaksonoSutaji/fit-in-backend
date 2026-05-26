<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\Admin\AdminController;

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

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| MIDTRANS WEBHOOK
|--------------------------------------------------------------------------
*/

Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (SEMENTARA TANPA AUTH UNTUK TESTING)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {

    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    Route::get('/users', [AdminController::class, 'users']);

    Route::patch('/users/{id}/make-admin', [AdminController::class, 'makeAdmin']);

    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', [AuthController::class, 'user']);

    /*
    |--------------------------------------------------------------------------
    | PROFILE
    |--------------------------------------------------------------------------
    */

    Route::post('/profile', [ProfileController::class, 'store']);

    Route::get('/profile', [ProfileController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | PAYMENT
    |--------------------------------------------------------------------------
    */

    Route::get('/packages', [PaymentController::class, 'packages']);

    Route::post('/payment/checkout', [PaymentController::class, 'checkout']);

    Route::post('/payment/success', [PaymentController::class, 'success']);

    Route::get('/payment/status', [PaymentController::class, 'status']);

    /*
    |--------------------------------------------------------------------------
    | WORKOUT
    |--------------------------------------------------------------------------
    */

    Route::get('/workouts', [WorkoutController::class, 'index']);

    Route::post('/workouts/generate', [WorkoutController::class, 'generate']);

    Route::patch('/workouts/{id}/done', [WorkoutController::class, 'markDone']);

    Route::get('/workouts/stats', [WorkoutController::class, 'stats']);

});
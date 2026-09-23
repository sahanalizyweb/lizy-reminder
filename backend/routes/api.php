<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // The assignable-staff list used by the Assigned Person dropdowns (is_staff accounts).
    // Not to be confused with /manage-users below, which is every login account.
    Route::get('/users', [StaffController::class, 'index']);
    Route::get('/product-categories', [ProductCategoryController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{reminder}/read', [NotificationController::class, 'markRead']);

    Route::get('/reminders/summary', [ReminderController::class, 'summary']);
    Route::get('/reminders/export', [ReminderController::class, 'export']);
    Route::post('/reminders/{reminder}/complete', [ReminderController::class, 'complete']);
    Route::get('/reminders/{reminder}/history', [ReminderController::class, 'history']);
    Route::apiResource('reminders', ReminderController::class);

    // Manage Users: Admin only. Named "user" (not the auto-derived "manage_user") so it
    // matches the {User $user} parameter used throughout the controller and requests.
    Route::apiResource('manage-users', UserController::class)
        ->parameters(['manage-users' => 'user'])
        ->middleware('admin');
});

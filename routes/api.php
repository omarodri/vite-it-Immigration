<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes with rate limiting
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge']);
});

Route::middleware('throttle:register')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

// Password reset routes with rate limiting
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

Route::get('/verify-token/{token}/{email}', [PasswordResetController::class, 'verifyToken']);

// Email verification (signed URL - no auth required)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:email-verification'])
    ->name('verification.verify');

// Protected routes (authentication required) with API rate limiting
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Email verification routes (auth required)
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:email-verification')
        ->name('verification.send');
    Route::get('/email/verification-status', [EmailVerificationController::class, 'status']);

    // Profile management routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::post('/profile/password', [ProfileController::class, 'changePassword']);

    // User management routes
    Route::apiResource('users', UserController::class);
    Route::delete('/users/bulk', [UserController::class, 'bulkDestroy']);

    // Role management routes
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    Route::get('/permissions', [RoleController::class, 'permissions']);

    // Activity log routes
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{activity}', [ActivityLogController::class, 'show']);

    // Two-factor authentication management routes
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable']);
    Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
    Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);

    // Tenant management routes
    Route::get('/tenant', [TenantController::class, 'show']);
    Route::put('/tenant/settings', [TenantController::class, 'updateSettings']);
    Route::put('/tenant/branding', [TenantController::class, 'updateBranding']);
});

// Public tenant branding route (no auth required)
Route::get('/tenant/{slug}/branding', [TenantController::class, 'branding']);

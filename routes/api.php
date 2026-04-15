<?php

/**
 * ResQMap API v1
 *
 * All routes here are prefixed with /api/v1/ (configured in bootstrap/app.php).
 *
 * Authentication: Laravel Sanctum token-based (Bearer token in Authorization header)
 * Flutter devices pass "device_name" when logging in; a unique token is issued per device.
 *
 * Rate limiting:
 *   - auth routes  → throttle:6,1   (6 attempts per minute)
 *   - api routes   → throttle:60,1  (60 requests per minute per user)
 */

use App\Http\Controllers\Api\V1\AIController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

// ─── Public auth routes ───────────────────────────────────────────────────────
Route::prefix('auth')->middleware('throttle:6,1')->group(function (): void {
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// ─── Protected routes (Sanctum token required) ───────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {

    // Auth ─────────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });

    // Profile ──────────────────────────────────────────────────────────────────
    Route::prefix('profile')->group(function (): void {
        Route::get('/',         [ProfileController::class, 'show']);
        Route::patch('/',       [ProfileController::class, 'update']);
        Route::patch('password', [ProfileController::class, 'updatePassword']);
    });

    // Location (Grab-style real-time tracking) ─────────────────────────────────
    Route::prefix('location')->group(function (): void {
        Route::post('/',          [LocationController::class, 'update']);       // push my GPS position
        Route::delete('/',        [LocationController::class, 'deactivate']);   // go offline
        Route::get('rescuers',    [LocationController::class, 'activeRescuers']); // all active rescuers
        Route::get('nearby',      [LocationController::class, 'nearby']);         // users near a point
    });

    // Incidents ────────────────────────────────────────────────────────────────
    Route::prefix('incidents')->group(function (): void {
        Route::get('/',        [IncidentController::class, 'index']);  // list (role-scoped)
        Route::post('/',       [IncidentController::class, 'store']);  // create report
        Route::get('nearby',   [IncidentController::class, 'nearby']); // active incidents near point
        Route::get('{incident}',          [IncidentController::class, 'show']);         // single incident
        Route::patch('{incident}/status', [IncidentController::class, 'updateStatus']); // change status
        Route::post('{incident}/assign',  [IncidentController::class, 'assign'])        // assign rescuer
            ->middleware('role:admin');
    });

    // Assignments (rescuer perspective) ────────────────────────────────────────
    Route::prefix('assignments')->middleware('role:rescuer')->group(function (): void {
        Route::get('/',                       [AssignmentController::class, 'index']);
        Route::patch('{assignment}/status',   [AssignmentController::class, 'updateStatus']);
    });

    // ResQBot AI (Cebuano emergency assistant) ─────────────────────────────────
    // Stricter rate limit: 30 AI calls per minute to manage OpenAI costs
    Route::prefix('ai')->middleware('throttle:30,1')->group(function (): void {
        Route::post('chat', [AIController::class, 'chat']); // GPT-4o-mini chat
        Route::post('tts',  [AIController::class, 'tts']);  // OpenAI TTS audio
    });
});

<?php

use App\Http\Controllers\Admin\IncidentWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Rescuer\AssignmentController as RescuerAssignmentController;
use App\Http\Controllers\Web\LocationController as WebLocationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('location/ping', [WebLocationController::class, 'ping'])->name('location.ping');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'adminDashboardOverview'])->name('dashboard');
        Route::get('incidents', [DashboardController::class, 'adminIncidentsIndex'])->name('incidents.index');
        Route::get('users', [DashboardController::class, 'adminUsers'])->name('users.index');
        Route::get('responders', [DashboardController::class, 'adminResponders'])->name('responders.index');
        Route::get('settings', [DashboardController::class, 'adminSettings'])->name('settings.index');
    });

    Route::middleware('role:admin')->prefix('admin/incidents')->name('admin.incidents.')->group(function (): void {
        Route::post('{incident}/ai-verify', [IncidentWorkflowController::class, 'aiVerify'])->name('ai-verify');
        Route::post('{incident}/retry-dispatch', [IncidentWorkflowController::class, 'retryDispatch'])->name('retry-dispatch');
        Route::patch('{incident}/status', [IncidentWorkflowController::class, 'updateStatus'])->name('status');
    });

    Route::middleware('role:rescuer')->prefix('rescuer')->name('rescuer.')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'rescuerDashboard'])->name('dashboard');
        Route::patch('assignments/{assignment}/status', [RescuerAssignmentController::class, 'updateStatus'])->name('assignments.status');
    });
});

require __DIR__.'/settings.php';

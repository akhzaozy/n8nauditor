<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

// Public Healthcheck Endpoint
Route::get('/health', [HealthController::class, 'index'])->name('health');

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Audits
    Route::get('/audits', [AuditController::class, 'index'])->name('audits.index');
    Route::get('/audits/{id}', [AuditController::class, 'show'])->name('audits.show');
    Route::post('/audits/trigger', [AuditController::class, 'triggerManual'])->name('audits.trigger');

    // Findings
    Route::get('/findings', [FindingController::class, 'index'])->name('findings.index');

    // Servers
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');
    Route::put('/servers/{server}', [ServerController::class, 'update'])->name('servers.update');
    Route::delete('/servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');
    Route::post('/servers/{server}/regenerate-token', [ServerController::class, 'regenerateToken'])->name('servers.token');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/test-ai', [SettingController::class, 'testAi'])->name('settings.test-ai');
});

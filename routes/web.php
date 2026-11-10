<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');
    Route::post('/dashboard/consejo-diario', [DashboardController::class, 'refreshDailyAdvice'])->name('dashboard.daily-advice');
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/history/live', [HistoryController::class, 'live'])->name('history.live');
    Route::get('/analysis', [AnalysisController::class, 'index'])->name('analysis');
    Route::post('/analysis', [AnalysisController::class, 'analyze'])->name('analysis.run');
    Route::get('/dispositivo', [DeviceController::class, 'index'])->name('device');
    Route::patch('/dispositivo/cultivo', [DeviceController::class, 'updateCrop'])->name('device.crop');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

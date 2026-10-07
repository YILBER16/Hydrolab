<?php

use App\Http\Controllers\Api\ReadingController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Hydrolab',
        'time' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->middleware(AuthenticateDevice::class)->group(function () {
    Route::get('/ping', [ReadingController::class, 'ping']);
    Route::post('/readings', [ReadingController::class, 'store']);
});

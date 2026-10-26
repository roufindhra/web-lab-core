<?php

use App\Http\Controllers\Client\PublicController;
use App\Http\Controllers\Client\SewaController;
use Illuminate\Support\Facades\Route;

Route::get('/praktikum', [PublicController::class, 'praktikum']);
Route::get('/akademik', [PublicController::class, 'akademik']);
Route::get('/agenda', [PublicController::class, 'agenda']);

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/sewa/options', [SewaController::class, 'options']);
    Route::post('/sewa', [SewaController::class, 'store']);
});

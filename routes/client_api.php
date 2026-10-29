<?php

use App\Http\Controllers\Client\AlatController;
use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\PeminjamanController;
use App\Http\Controllers\Client\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:mahasiswa,asisten'])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/ubah-password', [ProfileController::class, 'updatePassword']);
    Route::get('/alat', [AlatController::class, 'index']);
    Route::get('/peminjaman', [PeminjamanController::class, 'index']);
    Route::get('/peminjaman/options', [PeminjamanController::class, 'options']);
    Route::post('/peminjaman', [PeminjamanController::class, 'store']);
});

<?php

use App\Http\Controllers\Admin\AlatController;
use App\Http\Controllers\Admin\AlatLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HargaSewaController;
use App\Http\Controllers\Admin\KurikulumController;
use App\Http\Controllers\Admin\MahasiswaController;
use App\Http\Controllers\Admin\PeminjamanController;
use App\Http\Controllers\Admin\PertemuanController;
use App\Http\Controllers\Admin\PraktikumController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:dosen,asisten'])->group(function (): void {
    Route::get('/me',                   [AuthController::class, 'me']);
    Route::post('/logout',              [AuthController::class, 'logout']);
    Route::get('/dashboard',            [DashboardController::class, 'index']);
    Route::get('/mahasiswa',            [MahasiswaController::class, 'index']);
    Route::put('/mahasiswa/{mahasiswa}', [MahasiswaController::class, 'update']);
    Route::post('/mahasiswa/import',    [MahasiswaController::class, 'importCsv']);
    Route::get('/praktikum',            [PraktikumController::class, 'index']);
    Route::get('/praktikum/{praktikum:praktikum_slug}', [PraktikumController::class, 'show']);
    Route::post('/praktikum',           [PraktikumController::class, 'store']);
    Route::put('/praktikum/{praktikum}',    [PraktikumController::class, 'update']);
    Route::delete('/praktikum/{praktikum}', [PraktikumController::class, 'destroy']);
    Route::get('/praktikum/{praktikum}/plugs/{plug}/mahasiswa', [PraktikumController::class, 'listMahasiswa']);
    Route::put('/praktikum/{praktikum}/plugs/{plug}/mahasiswa', [PraktikumController::class, 'syncMahasiswa']);
    Route::put('/praktikum/{praktikum:praktikum_slug}/mahasiswa/{mahasiswa}/kelompok', [PraktikumController::class, 'updateKelompok']);
    Route::get('/praktikum/{praktikum:praktikum_slug}/kurikulum',            [KurikulumController::class, 'index']);
    Route::post('/praktikum/{praktikum:praktikum_slug}/kurikulum',           [KurikulumController::class, 'store']);
    Route::put('/praktikum/{praktikum:praktikum_slug}/kurikulum/{kurikulum}', [KurikulumController::class, 'update'])->scopeBindings();
    Route::delete('/praktikum/{praktikum:praktikum_slug}/kurikulum/{kurikulum}', [KurikulumController::class, 'destroy'])->scopeBindings();
    Route::post('/praktikum/{praktikum:praktikum_slug}/kurikulum/{kurikulum}/restore', [KurikulumController::class, 'restore'])->scopeBindings();
    Route::get('/praktikum/{praktikum:praktikum_slug}/pertemuan',          [PertemuanController::class, 'index']);
    Route::post('/praktikum/{praktikum:praktikum_slug}/pertemuan',         [PertemuanController::class, 'store']);
    Route::put('/praktikum/{praktikum:praktikum_slug}/pertemuan/{pertemuan}',   [PertemuanController::class, 'update']);
    Route::delete('/praktikum/{praktikum:praktikum_slug}/pertemuan/{pertemuan}', [PertemuanController::class, 'destroy']);
    Route::get('/praktikum/{praktikum:praktikum_slug}/plugs/{plug}/nilai', [PertemuanController::class, 'nilaiPlug']);
    Route::put('/praktikum/{praktikum:praktikum_slug}/plugs/{plug}/pertemuan/{pertemuan}/nilai', [PertemuanController::class, 'saveNilai']);
    Route::get('/alat',              [AlatController::class, 'index']);
    Route::post('/alat',             [AlatController::class, 'store']);
    Route::put('/alat/{alat}',       [AlatController::class, 'update']);
    Route::delete('/alat/{alat}',    [AlatController::class, 'destroy']);
    Route::get('/alat-log',          [AlatLogController::class, 'index']);
    Route::get('/peminjaman',                 [PeminjamanController::class, 'index']);
    Route::get('/peminjaman/alat-options',    [PeminjamanController::class, 'alatOptions']);
    Route::put('/peminjaman/{peminjaman}/items',       [PeminjamanController::class, 'revisiItems']);
    Route::post('/peminjaman/{peminjaman}/setujui',    [PeminjamanController::class, 'setujui']);
    Route::post('/peminjaman/{peminjaman}/tolak',      [PeminjamanController::class, 'tolak']);
    Route::post('/peminjaman/{peminjaman}/kembalikan', [PeminjamanController::class, 'kembalikan']);
    Route::apiResource('harga-sewa', HargaSewaController::class);
});

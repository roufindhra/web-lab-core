<?php

use App\Http\Controllers\Client\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/praktikum', [PublicController::class, 'praktikum']);
Route::get('/agenda', [PublicController::class, 'agenda']);

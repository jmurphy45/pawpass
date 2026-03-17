<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/refresh', [LoginController::class, 'refresh']);
Route::post('/auth/logout', [LoginController::class, 'logout']);

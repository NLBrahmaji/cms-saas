<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [RegisterController::class, 'store']);
Route::post('/auth/login', [LoginController::class, 'store']);
Route::post('/auth/logout', [LogoutController::class, 'destroy'])->middleware('auth:sanctum');

Route::get('/user', [AuthenticatedUserController::class, 'show'])->middleware('auth:sanctum');
Route::get('/accounts', [AccountController::class, 'index'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'account.member'])->group(function () {
    Route::get('/accounts/{account}', [AccountController::class, 'show']);
});

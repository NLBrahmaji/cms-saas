<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WebsiteSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [RegisterController::class, 'store']);
    Route::post('/auth/login', [LoginController::class, 'store']);
    Route::post('/auth/logout', [LogoutController::class, 'destroy'])->middleware('auth:sanctum');

    Route::get('/user', [AuthenticatedUserController::class, 'show'])->middleware('auth:sanctum');
    Route::get('/accounts', [AccountController::class, 'index'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'account.member'])->scopeBindings()->group(function () {
        Route::get('/accounts/{account}', [AccountController::class, 'show']);

        Route::get('/accounts/{account}/websites', [WebsiteController::class, 'index']);
        Route::post('/accounts/{account}/websites', [WebsiteController::class, 'store']);
        Route::get('/accounts/{account}/websites/{website}', [WebsiteController::class, 'show']);
        Route::patch('/accounts/{account}/websites/{website}', [WebsiteController::class, 'update']);
        Route::put('/accounts/{account}/websites/{website}/homepage', [WebsiteController::class, 'updateHomepage']);
        Route::delete('/accounts/{account}/websites/{website}', [WebsiteController::class, 'destroy']);

        Route::get('/accounts/{account}/websites/{website}/settings', [WebsiteSettingController::class, 'show']);
        Route::patch('/accounts/{account}/websites/{website}/settings', [WebsiteSettingController::class, 'update']);

        Route::get('/accounts/{account}/websites/{website}/pages', [PageController::class, 'index']);
        Route::post('/accounts/{account}/websites/{website}/pages', [PageController::class, 'store']);
        Route::get('/accounts/{account}/websites/{website}/pages/{page}', [PageController::class, 'show']);
        Route::patch('/accounts/{account}/websites/{website}/pages/{page}', [PageController::class, 'update']);
        Route::delete('/accounts/{account}/websites/{website}/pages/{page}', [PageController::class, 'destroy']);
        Route::post('/accounts/{account}/websites/{website}/pages/{page}/publish', [PageController::class, 'publish']);
    });
});

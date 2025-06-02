<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\CertificateApiController;
use App\Http\Controllers\SquareWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 認証不要のルート
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
});

// 認証が必要なルート
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // 証明書API
    Route::get('/certificates', [CertificateApiController::class, 'index']);
    Route::post('/certificates/{product}', [CertificateApiController::class, 'store']);
    Route::get('/certificates/{order}', [CertificateApiController::class, 'show']);
    Route::get('/certificates/{order}/download', [CertificateApiController::class, 'download']);
    Route::post('/certificates/{order}/reissue', [CertificateApiController::class, 'reissue']);
    
    // サブスクリプションAPI
    Route::get('/subscriptions', [CertificateApiController::class, 'subscriptions']);
    Route::get('/subscriptions/{subscription}', [CertificateApiController::class, 'showSubscription']);
    Route::delete('/subscriptions/{subscription}/cancel', [CertificateApiController::class, 'cancelSubscription']);
    Route::patch('/subscriptions/{subscription}/pause', [CertificateApiController::class, 'pauseSubscription']);
    Route::patch('/subscriptions/{subscription}/resume', [CertificateApiController::class, 'resumeSubscription']);

});

Route::post('/webhooks/square', [SquareWebhookController::class, 'handle']);

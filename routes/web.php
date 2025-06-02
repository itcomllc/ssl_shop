<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\SquareWebhookController;

Route::get('/', function () {
    return redirect('/auth');
});

Route::get('/auth', function () {
    return view('auth');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

    // 既存の証明書ルート
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/create/{product}', [CertificateController::class, 'create'])->name('certificates.create');
    Route::post('/certificates/{product}', [CertificateController::class, 'store'])->name('certificates.store');
    Route::get('/certificates/{order}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{order}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::post('/certificates/{order}/reissue', [CertificateController::class, 'reissue'])->name('certificates.reissue');
    
    // サブスクリプション管理ルート
    Route::get('/subscriptions', [CertificateController::class, 'subscriptions'])->name('certificates.subscriptions');
    Route::get('/subscriptions/{subscription}', [CertificateController::class, 'showSubscription'])->name('certificates.subscription.show');
    Route::delete('/subscriptions/{subscription}/cancel', [CertificateController::class, 'cancelSubscription'])->name('certificates.subscription.cancel');
    Route::patch('/subscriptions/{subscription}/pause', [CertificateController::class, 'pauseSubscription'])->name('certificates.subscription.pause');
    Route::patch('/subscriptions/{subscription}/resume', [CertificateController::class, 'resumeSubscription'])->name('certificates.subscription.resume');
Route::middleware(['auth:sanctum'])->group(function () {
});

Route::post('/webhooks/square', [SquareWebhookController::class, 'handle'])->name('webhooks.square');

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/auth');
});

Route::get('/auth', function () {
    return view('auth');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

// 他のページのプレースホルダー
Route::get('/certificates', function () {
    return view('certificates');
});

Route::get('/subscriptions', function () {
    return view('subscriptions');
});
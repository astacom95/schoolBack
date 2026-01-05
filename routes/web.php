<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YouTubeAuthController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/fatash', function () {
    return view('fatash');
});

Route::get('/youtube/connect', [YouTubeAuthController::class, 'redirect'])->name('youtube.connect');
Route::get('/youtube/callback', [YouTubeAuthController::class, 'callback'])->name('youtube.callback');
Route::get('/api/auth/youtube/redirect', [YouTubeAuthController::class, 'redirect'])->name('youtube.connect.legacy');
Route::get('/api/auth/youtube/callback', [YouTubeAuthController::class, 'callback'])->name('youtube.callback.legacy');

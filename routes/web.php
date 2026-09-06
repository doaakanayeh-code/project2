<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\User; // استدعاء موديل المستخدم لحفظ البيانات

Route::get('/', function () {
    return view('welcome');
});

// روابط جوجل
Route::get('/auth/google/redirect', [SocialiteController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback']);


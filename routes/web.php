<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', [HomeController::class, 'upload']);
Route::post('/upload/init', [HomeController::class, 'initUpload']);
Route::post('/upload/chunk', [HomeController::class, 'uploadChunk']);
Route::post('/upload/finalize', [HomeController::class, 'finalizeUpload']);

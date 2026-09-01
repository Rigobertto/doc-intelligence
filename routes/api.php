<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FailedFileController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('file', FileController::class)->only(['store', 'index']);
Route::get('failed-file', [FailedFileController::class, 'index']);
Route::post('fix-file/{id}', [FailedFileController::class, 'fix']);

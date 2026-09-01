<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FailedFileController;
use App\Http\Controllers\FailedJobController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('file', FileController::class)->only(['store', 'index', 'destroy']);
Route::get('file-search', [FileController::class, 'search']);
Route::get('failed-file', [FailedFileController::class, 'index']);
Route::delete('failed-file/{id}', [FailedFileController::class, 'destroy']);
Route::post('fix-file/{id}', [FailedFileController::class, 'fix']);

Route::get('failed-jobs', [FailedJobController::class, 'index']);
Route::post('failed-jobs/retry', [FailedJobController::class, 'retry']);

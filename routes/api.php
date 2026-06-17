<?php

use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::put('/organization', [OrganizationController::class, 'store']);
    Route::post('/organization/sync', [OrganizationController::class, 'sync']);
    Route::get('/organization/reviews', [OrganizationController::class, 'reviews']);
});

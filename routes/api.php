<?php

use App\Http\Controllers\PosApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->middleware('throttle:120,1')->group(function () {
    Route::get('/connection', [PosApiController::class,'connection']);
    Route::post('/reconciliation', [PosApiController::class,'reconcile']);
    Route::get('/members', [PosApiController::class,'member']);
    Route::post('/redemptions', [PosApiController::class,'reserve']);
    Route::post('/redemptions/{key}/release', [PosApiController::class,'release']);
    Route::post('/sales', [PosApiController::class,'sale']);
    Route::post('/sales/{externalId}/refund', [PosApiController::class,'refund']);
});

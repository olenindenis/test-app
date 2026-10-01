<?php

use App\Http\Controllers\EarningLineController;
use Illuminate\Support\Facades\Route;

// Corrections are append-only: there are deliberately no PUT/PATCH/DELETE routes.
Route::controller(EarningLineController::class)->prefix('earning-lines')->whereUuid('lineId')->group(function () {
    Route::post('/', 'store');
    Route::get('/{lineId}', 'show');
    Route::get('/{lineId}/events', 'events');
    Route::post('/{lineId}/recalculations', 'recalculate');
    Route::post('/{lineId}/adjustments', 'addAdjustment');
});

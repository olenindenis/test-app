<?php

use App\Http\Controllers\EarningLineController;
use Illuminate\Support\Facades\Route;

Route::controller(EarningLineController::class)->prefix('earning-lines')->whereUuid('lineId')->group(function () {
    Route::post('/', 'store');
    Route::get('/{lineId}', 'show');
    Route::get('/{lineId}/events', 'events');
    Route::post('/{lineId}/recalculations', 'recalculate');
    Route::post('/{lineId}/adjustments', 'addAdjustment');
});

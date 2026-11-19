<?php

use App\Http\Controllers\ForecastController;
use App\Http\Controllers\ForecastRunController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

Route::get('locations', [LocationController::class, 'index']);

Route::prefix('locations/{location}/forecasts')->where(['location' => '[0-9]{1,18}'])->group(function () {
    Route::get('daily', [ForecastController::class, 'daily']);
    Route::get('hourly', [ForecastController::class, 'hourly']);
    Route::get('daily/{date}/revisions', [ForecastController::class, 'revisions'])
        ->where('date', '\d{4}-\d{2}-\d{2}');
});

Route::get('forecast-runs', [ForecastRunController::class, 'index']);

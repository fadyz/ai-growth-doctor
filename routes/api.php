<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiGrowthDoctorController;

Route::middleware(['demo.basic_auth'])->group(function () {
    Route::post('/ai-growth-doctor/analyze', [AiGrowthDoctorController::class, 'analyze']);
    Route::post('/ai-growth-doctor/analyze-async/start', [AiGrowthDoctorController::class, 'startAsync']);
    Route::get('/ai-growth-doctor/runs/{runId}', [AiGrowthDoctorController::class, 'getRunStatus']);
});

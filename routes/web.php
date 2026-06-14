<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiGrowthDoctorController;
use App\Http\Controllers\AiGrowthDoctorGraphController;

Route::get('/', function () {
    return redirect('/ai-growth-doctor');
});

Route::middleware(['demo.basic_auth'])->group(function () {
    Route::get('/ai-growth-doctor', [AiGrowthDoctorController::class, 'dashboard']);
    Route::get('/ai-growth-doctor/runs/{runId}/graph-view', [AiGrowthDoctorGraphController::class, 'show'])
        ->name('ai-growth-doctor.runs.graph-view');
    Route::get('/ai-growth-doctor/runs/{runId}/graph', [AiGrowthDoctorGraphController::class, 'graph'])
        ->name('ai-growth-doctor.runs.graph');
    Route::get('/ai-growth-doctor/runs/{runId}/audit', [AiGrowthDoctorController::class, 'downloadAuditTrace'])
        ->name('ai-growth-doctor.runs.audit');
});

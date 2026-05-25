<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiGrowthDoctorController;

Route::get('/', function () {
    return redirect('/ai-growth-doctor');
});

Route::get('/ai-growth-doctor', [AiGrowthDoctorController::class, 'dashboard']);
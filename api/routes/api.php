<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CycleAnalysisController;
use App\Http\Controllers\CycleCalendarController;
use App\Http\Controllers\CycleController;
use App\Http\Controllers\CycleStatusController;
use App\Http\Controllers\DayLogController;
use App\Http\Controllers\ExclusionPeriodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\TrainingSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    Route::get('/cycle-status', CycleStatusController::class);
    Route::get('/cycle-calendar', CycleCalendarController::class);
    Route::get('/cycle-analysis', CycleAnalysisController::class);

    Route::apiResource('cycles', CycleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('day-logs', DayLogController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('exclusion-periods', ExclusionPeriodController::class)->only(['index', 'store', 'update', 'destroy']);

    // Moteur B — training programs
    Route::apiResource('programs', ProgramController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('training-sessions', TrainingSessionController::class)->only(['index', 'show', 'update']);
});

<?php

use App\Http\Controllers\Api\FoldersController;
use App\Http\Controllers\Api\ProjectLessonsController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.access-token')->group(function (): void {
    Route::get('folders', [FoldersController::class, 'index']);

    Route::apiResource('projects.lessons', ProjectLessonsController::class)
        ->only(['index', 'store'])
        ->whereNumber('project');
});

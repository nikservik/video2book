<?php

use App\Http\Controllers\LessonController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PipelineRunController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTagController;
use App\Http\Controllers\StepController;
use Illuminate\Support\Facades\Route;

Route::prefix('pipelines')->group(function (): void {
    Route::get('/', [PipelineController::class, 'index']);
    Route::post('/', [PipelineController::class, 'store']);

    Route::get('{pipeline}/versions', [PipelineController::class, 'versions']);
    Route::get('{pipeline}', [PipelineController::class, 'show']);
    Route::put('{pipeline}', [PipelineController::class, 'update']);
    Route::post('{pipeline}/archive', [PipelineController::class, 'archive']);

    Route::post('{pipeline}/steps/reorder', [PipelineController::class, 'reorderSteps']);
    Route::post('{pipeline}/steps', [PipelineController::class, 'addStep']);
    Route::post('{pipeline}/steps/{step}/initial-version', [PipelineController::class, 'createInitialStepVersion']);
    Route::post('{pipeline}/steps/{step}/versions', [PipelineController::class, 'updateStep']);
    Route::delete('{pipeline}/steps/{step}', [PipelineController::class, 'removeStep']);
});

Route::get('pipeline-versions/{pipelineVersion}/steps', [PipelineController::class, 'pipelineVersionSteps']);

Route::get('steps/{step}/versions', [StepController::class, 'versions']);

Route::prefix('pipeline-runs')->group(function (): void {
    Route::get('queue', [PipelineRunController::class, 'queue']);
    Route::get('events/queue', [PipelineRunController::class, 'queueEvents']);
    Route::post('/', [PipelineRunController::class, 'store']);
    Route::get('{pipelineRun}/events', [PipelineRunController::class, 'runEvents'])
        ->whereNumber('pipelineRun');
    Route::get('{pipelineRun}/steps/{step}/export/pdf', [PipelineRunController::class, 'exportStepPdf'])
        ->whereNumber(['pipelineRun', 'step']);
    Route::get('{pipelineRun}/steps/{step}/export/md', [PipelineRunController::class, 'exportStepMarkdown'])
        ->whereNumber(['pipelineRun', 'step']);
    Route::get('{pipelineRun}', [PipelineRunController::class, 'show'])
        ->whereNumber('pipelineRun');
    Route::post('{pipelineRun}/restart', [PipelineRunController::class, 'restart']);
});

Route::prefix('lessons')->group(function (): void {
    Route::get('/', [LessonController::class, 'index']);
    Route::post('/', [LessonController::class, 'store']);
    Route::put('{lesson}', [LessonController::class, 'update']);
    Route::post('{lesson}/audio', [LessonController::class, 'uploadAudio']);
    Route::post('{lesson}/download', [LessonController::class, 'download']);
});

Route::prefix('projects')->group(function (): void {
    Route::get('/', [ProjectController::class, 'index']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::post('/youtube', [ProjectController::class, 'storeYoutube']);
    Route::put('{project}', [ProjectController::class, 'update']);
});

Route::prefix('project-tags')->group(function (): void {
    Route::get('/', [ProjectTagController::class, 'index']);
    Route::post('/', [ProjectTagController::class, 'store']);
    Route::put('{projectTag}', [ProjectTagController::class, 'update']);
    Route::delete('{projectTag}', [ProjectTagController::class, 'destroy']);
});

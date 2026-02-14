<?php

use App\Livewire\CreatePipelinePage;
use App\Livewire\HomePage;
use App\Livewire\PipelinesPage;
use App\Livewire\PipelineStepPage;
use App\Livewire\ProjectLessonPage;
use App\Livewire\ProjectRunPage;
use App\Livewire\ProjectShowPage;
use App\Livewire\ProjectsPage;
use Illuminate\Support\Facades\Route;

Route::get('/', HomePage::class)->name('home');

Route::prefix('projects')->name('projects.')->group(function (): void {
    Route::get('/', ProjectsPage::class)->name('index');
    Route::get('/{project}', ProjectShowPage::class)->name('show');
    Route::get('/{project}/runs/{pipelineRun}', ProjectRunPage::class)->name('runs.show');
    Route::get('/{project}/lessons/{lesson}', ProjectLessonPage::class)->name('lessons.show');
});

Route::prefix('pipelines')->name('pipelines.')->group(function (): void {
    Route::get('/', PipelinesPage::class)->name('index');
    Route::get('/create', CreatePipelinePage::class)->name('create');
    Route::get('/{pipeline}/steps/{step}', PipelineStepPage::class)->name('steps.show');
});

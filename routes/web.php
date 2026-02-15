<?php

use App\Livewire\HomePage;
use App\Livewire\PipelineShowPage;
use App\Livewire\PipelinesPage;
use App\Livewire\ProjectRunPage;
use App\Livewire\ProjectShowPage;
use App\Livewire\ProjectsPage;
use Illuminate\Support\Facades\Route;

Route::get('/', HomePage::class)->name('home');

Route::prefix('projects')->name('projects.')->group(function (): void {
    Route::get('/', ProjectsPage::class)->name('index');
    Route::get('/{project}', ProjectShowPage::class)->name('show');
    Route::get('/{project}/runs/{pipelineRun}', ProjectRunPage::class)->name('runs.show');
});

Route::prefix('pipelines')->name('pipelines.')->group(function (): void {
    Route::get('/', PipelinesPage::class)->name('index');
    Route::get('/{pipeline}', PipelineShowPage::class)->name('show');
});

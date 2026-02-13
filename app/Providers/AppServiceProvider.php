<?php

namespace App\Providers;

use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmManager;
use App\Services\Llm\LlmPricing;
use App\Services\Pipeline\Contracts\PipelineStepExecutor;
use App\Services\Pipeline\DefaultPipelineStepExecutor;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager as LaravelAiManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LlmPricing::class, function ($app): LlmPricing {
            return new LlmPricing($app->make('config'));
        });

        $this->app->singleton(LlmCostCalculator::class, function ($app): LlmCostCalculator {
            return new LlmCostCalculator($app->make(LlmPricing::class));
        });

        $this->app->singleton(LlmManager::class, function ($app): LlmManager {
            return new LlmManager(
                ai: $app->make(LaravelAiManager::class),
                costCalculator: $app->make(LlmCostCalculator::class),
                providers: array_keys((array) config('ai.providers', [])),
                requestTimeout: (int) config('llm.request_timeout', 1800),
                anthropicMaxTokens: (int) config('llm.defaults.anthropic.max_tokens', 64000),
            );
        });

        $this->app->singleton(PipelineStepExecutor::class, DefaultPipelineStepExecutor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

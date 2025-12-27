<?php

namespace App\Services\Llm;

use Illuminate\Contracts\Config\Repository;

final class LlmPricing
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function costForTokens(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        string $inputType = LlmRequest::INPUT_TYPE_TEXT,
    ): float {
        /** @var array<string, mixed> $models */
        $models = (array) $this->config->get("pricing.providers.$provider.models", []);
        $modelConfig = $models[$model] ?? null;

        if ($modelConfig === null) {
            return 0.0;
        }

        $inputRate = $this->resolveRate($modelConfig['input'] ?? null, $inputType);
        $outputRate = $this->resolveRate($modelConfig['output'] ?? null, $inputType);

        return ($inputRate ?? 0.0) * $inputTokens + ($outputRate ?? 0.0) * $outputTokens;
    }

    public function whisperCost(float $minutes): float
    {
        $rate = (float) $this->config->get('pricing.whisper.price_per_minute', 0.0);

        return $minutes * $rate;
    }

    private function resolveRate(null|float|array $rateConfig, string $inputType): ?float
    {
        if (is_array($rateConfig)) {
            $normalized = $this->normalizeInputType($inputType);

            if (array_key_exists($normalized, $rateConfig)) {
                return (float) $rateConfig[$normalized];
            }

            if (array_key_exists('text', $rateConfig)) {
                return (float) $rateConfig['text'];
            }

            $first = reset($rateConfig);

            return $first === false ? null : (float) $first;
        }

        if ($rateConfig === null) {
            return null;
        }

        return (float) $rateConfig;
    }

    private function normalizeInputType(string $inputType): string
    {
        return match ($inputType) {
            LlmRequest::INPUT_TYPE_AUDIO => 'audio',
            default => 'text',
        };
    }
}

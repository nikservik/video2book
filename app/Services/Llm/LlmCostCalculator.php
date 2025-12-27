<?php

namespace App\Services\Llm;

final class LlmCostCalculator
{
    public function __construct(private readonly LlmPricing $pricing)
    {
    }

    public function calculateUsageCost(string $provider, string $model, LlmUsage $usage, string $inputType = LlmRequest::INPUT_TYPE_TEXT): LlmUsage
    {
        $cost = $this->pricing->costForTokens(
            provider: $provider,
            model: $model,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            inputType: $inputType,
        );

        return new LlmUsage(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cost: $cost,
            meta: $usage->meta,
        );
    }

    public function whisper(float $minutes): float
    {
        return $this->pricing->whisperCost($minutes);
    }
}

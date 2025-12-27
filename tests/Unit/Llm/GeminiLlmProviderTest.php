<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmPricing;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\Providers\GeminiLlmProvider;
use Gemini\Contracts\ClientContract;
use Gemini\Contracts\Resources\GenerativeModelContract;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class GeminiLlmProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_non_stream_request_builds_generation_config(): void
    {
        $client = Mockery::mock(ClientContract::class);
        $model = Mockery::mock(GenerativeModelContract::class);

        $client->shouldReceive('generativeModel')->with('gemini-2.5-pro')->andReturn($model);
        $model->shouldReceive('withSystemInstruction')->andReturnSelf();
        $model->shouldReceive('withGenerationConfig')->andReturnSelf();

        $response = GenerateContentResponse::from([
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [['text' => 'Gemini answer']],
                ],
                'finishReason' => 'STOP',
                'safetyRatings' => [],
                'citationMetadata' => ['citationSources' => []],
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 20,
                'totalTokenCount' => 30,
                'candidatesTokenCount' => 10,
                'cachedContentTokenCount' => 0,
                'toolUsePromptTokenCount' => 0,
                'thoughtsTokenCount' => 0,
                'promptTokensDetails' => [],
                'cacheTokensDetails' => [],
                'candidatesTokensDetails' => [],
                'toolUsePromptTokensDetails' => [],
            ],
        ]);

        $model->shouldReceive('generateContent')->andReturn($response);

        $provider = new GeminiLlmProvider($client, $this->calculator());
        $request = new LlmRequest(
            model: 'gemini-2.5-pro',
            messages: [
                LlmMessage::system('guardrails'),
                LlmMessage::user('Summarize this.'),
            ],
            temperature: 0.1,
            stream: false,
            options: ['max_output_tokens' => 256],
        );

        $result = $provider->send($request);

        $this->assertSame('Gemini answer', $result->collect());
        $this->assertSame(10, $result->usage()?->outputTokens);
        $expectedCost = 0.00000125 * 20 + 0.00001 * 10;
        $this->assertEqualsWithDelta($expectedCost, $result->usage()?->cost, 1e-9);
    }

    private function calculator(): LlmCostCalculator
    {
        $config = new Repository([
            'pricing' => require __DIR__.'/../../../config/pricing.php',
        ]);

        return new LlmCostCalculator(new LlmPricing($config));
    }
}

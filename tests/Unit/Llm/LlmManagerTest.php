<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmManager;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmPricing;
use App\Services\Llm\LlmRequest;
use Illuminate\Config\Repository;
use InvalidArgumentException;
use Laravel\Ai\AiManager as LaravelAiManager;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class LlmManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_non_streaming_request_uses_laravel_ai_gateway(): void
    {
        $ai = Mockery::mock(LaravelAiManager::class);
        $textProvider = Mockery::mock(TextProvider::class);
        $gateway = Mockery::mock(TextGateway::class);

        $ai->shouldReceive('textProvider')
            ->once()
            ->with('openai')
            ->andReturn($textProvider);

        $textProvider->shouldReceive('textGateway')
            ->once()
            ->andReturn($gateway);

        $gateway->shouldReceive('generateText')
            ->once()
            ->withArgs(function (
                TextProvider $provider,
                string $model,
                ?string $instructions,
                array $messages,
                array $tools,
                ?array $schema,
                mixed $options,
                ?int $timeout
            ) use ($textProvider): bool {
                return $provider === $textProvider
                    && $model === 'gpt-5.2'
                    && $instructions === 'Stay on topic'
                    && count($messages) === 1
                    && $messages[0]->role->value === 'user'
                    && $messages[0]->content === 'Hello?'
                    && $tools === []
                    && $schema === null
                    && $timeout === 1800;
            })
            ->andReturn(new TextResponse(
                text: 'Hi there',
                usage: new Usage(promptTokens: 10, completionTokens: 5),
                meta: new Meta(provider: 'openai', model: 'gpt-5.2'),
            ));

        $manager = new LlmManager(
            ai: $ai,
            costCalculator: $this->calculator(),
            providers: ['openai'],
        );

        $result = $manager->send('openai', new LlmRequest(
            model: 'gpt-5.2',
            messages: [
                LlmMessage::system('Stay on topic'),
                LlmMessage::user('Hello?'),
            ],
            temperature: 0.2,
            stream: false,
        ));

        $this->assertSame('Hi there', $result->collect());
        $this->assertSame(10, $result->usage()?->inputTokens);
        $this->assertSame(5, $result->usage()?->outputTokens);
        $this->assertEqualsWithDelta(
            expected: 0.00000175 * 10 + 0.000014 * 5,
            actual: $result->usage()?->cost,
            delta: 1e-9,
        );
    }

    public function test_streaming_request_maps_deltas_and_usage(): void
    {
        $ai = Mockery::mock(LaravelAiManager::class);
        $textProvider = Mockery::mock(TextProvider::class);
        $gateway = Mockery::mock(TextGateway::class);

        $ai->shouldReceive('textProvider')
            ->once()
            ->with('openai')
            ->andReturn($textProvider);

        $textProvider->shouldReceive('textGateway')
            ->once()
            ->andReturn($gateway);

        $gateway->shouldReceive('streamText')
            ->once()
            ->andReturn((function () {
                yield new TextDelta('1', 'm1', 'Hel', 1);
                yield new TextDelta('2', 'm1', 'lo', 2);
                yield new StreamEnd('3', 'stop', new Usage(promptTokens: 3, completionTokens: 2), 3);
            })());

        $manager = new LlmManager(
            ai: $ai,
            costCalculator: $this->calculator(),
            providers: ['openai'],
        );

        $result = $manager->send('openai', new LlmRequest(
            model: 'gpt-5.2',
            messages: [LlmMessage::user('Hi')],
            stream: true,
        ));

        $chunks = [];

        $this->assertSame('Hello', $result->stream(function ($chunk) use (&$chunks): void {
            $chunks[] = $chunk->content;
        }));

        $this->assertSame(['Hel', 'lo', ''], $chunks);
        $this->assertSame(3, $result->usage()?->inputTokens);
        $this->assertSame(2, $result->usage()?->outputTokens);
    }

    public function test_missing_provider_throws(): void
    {
        $manager = new LlmManager(
            ai: Mockery::mock(LaravelAiManager::class),
            costCalculator: $this->calculator(),
            providers: [],
        );

        $this->expectException(InvalidArgumentException::class);

        $manager->send('missing', new LlmRequest('gpt-5-mini', []));
    }

    private function calculator(): LlmCostCalculator
    {
        $config = new Repository([
            'pricing' => require __DIR__.'/../../../config/pricing.php',
        ]);

        return new LlmCostCalculator(new LlmPricing($config));
    }
}

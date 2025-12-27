<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmPricing;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\Providers\OpenAiLlmProvider;
use Illuminate\Config\Repository;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\ChatContract;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Responses\StreamResponse as OpenAIStreamResponse;
use PHPUnit\Framework\TestCase;

class OpenAiLlmProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_non_streaming_request_returns_combined_text(): void
    {
        $client = Mockery::mock(ClientContract::class);
        $chat = Mockery::mock(ChatContract::class);
        $client->shouldReceive('chat')->andReturn($chat);

        $response = CreateResponse::from([
            'id' => '1',
            'object' => 'chat.completion',
            'created' => 1,
            'model' => 'gpt-test',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ], MetaInformation::from([]));

        $chat->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['model'] === 'gpt-5-mini'
                    && count($payload['messages']) === 2
                    && $payload['messages'][0]['role'] === 'system';
            })
            ->andReturn($response);

        $provider = new OpenAiLlmProvider($client, $this->calculator());
        $request = new LlmRequest(
            model: 'gpt-5-mini',
            messages: [
                LlmMessage::system('Stay on topic'),
                LlmMessage::user('Hello?'),
            ],
            temperature: 0.5,
            stream: false,
        );

        $result = $provider->send($request);

        $this->assertSame('Hello', $result->collect());
        $this->assertEqualsWithDelta(
            expected: 0.00000025 * 10 + 0.000002 * 5,
            actual: $result->usage()?->cost,
            delta: 1e-9,
        );
        $this->assertSame(5, $result->usage()?->outputTokens);
    }

    public function test_streaming_request_emits_chunks_and_usage(): void
    {
        $client = Mockery::mock(ClientContract::class);
        $chat = Mockery::mock(ChatContract::class);
        $client->shouldReceive('chat')->andReturn($chat);

        $events = [
            [
                'object' => 'chat.completion.chunk',
                'created' => 1,
                'model' => 'gpt-test',
                'choices' => [[
                    'index' => 0,
                    'delta' => ['content' => 'Hel'],
                    'finish_reason' => null,
                ]],
            ],
            [
                'object' => 'chat.completion.chunk',
                'created' => 2,
                'model' => 'gpt-test',
                'choices' => [[
                    'index' => 0,
                    'delta' => ['content' => 'lo'],
                    'finish_reason' => null,
                ]],
            ],
            [
                'object' => 'chat.completion.chunk',
                'created' => 3,
                'model' => 'gpt-test',
                'choices' => [[
                    'index' => 0,
                    'delta' => [],
                    'finish_reason' => 'stop',
                ]],
                'usage' => [
                    'prompt_tokens' => 3,
                    'completion_tokens' => 2,
                    'total_tokens' => 5,
                ],
            ],
        ];

        $body = '';
        foreach ($events as $event) {
            $body .= 'data: '.json_encode($event)."\n\n";
        }
        $body .= "data: [DONE]\n\n";

        $streamResponse = new OpenAIStreamResponse(
            CreateStreamedResponse::class,
            new Response(200, [], $body),
        );

        $chat->shouldReceive('createStreamed')
            ->once()
            ->andReturn($streamResponse);

        $provider = new OpenAiLlmProvider($client, $this->calculator());
        $request = new LlmRequest(model: 'gpt-5-mini', messages: [LlmMessage::user('Hi')]);

        $result = $provider->send($request);

        $chunks = [];
        $this->assertSame('Hello', $result->stream(function ($chunk) use (&$chunks): void {
            $chunks[] = $chunk->content;
        }));
        $this->assertSame(['Hel', 'lo', ''], $chunks);
        $expectedCost = 0.00000025 * 3 + 0.000002 * 2;
        $this->assertEqualsWithDelta($expectedCost, $result->usage()?->cost, 1e-9);
        $this->assertSame(2, $result->usage()?->outputTokens);
    }
    private function calculator(): LlmCostCalculator
    {
        $config = new Repository([
            'pricing' => require __DIR__.'/../../../config/pricing.php',
        ]);

        return new LlmCostCalculator(new LlmPricing($config));
    }
}

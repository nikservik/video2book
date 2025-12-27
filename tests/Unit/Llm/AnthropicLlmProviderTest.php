<?php

namespace Tests\Unit\Llm;

use Anthropic\Contracts\ClientContract;
use Anthropic\Contracts\Resources\MessagesContract;
use Anthropic\Responses\Messages\CreateResponse;
use Anthropic\Responses\Meta\MetaInformation;
use App\Services\Llm\LlmCostCalculator;
use App\Services\Llm\LlmMessage;
use App\Services\Llm\LlmPricing;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\Providers\AnthropicLlmProvider;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class AnthropicLlmProviderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_payload_contains_system_message_and_returns_text(): void
    {
        $client = Mockery::mock(ClientContract::class);
        $messages = Mockery::mock(MessagesContract::class);
        $client->shouldReceive('messages')->andReturn($messages);

        $response = CreateResponse::from([
            'id' => '1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-4-5',
            'stop_sequence' => null,
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'text', 'text' => 'Answer'],
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
            ],
        ], MetaInformation::from([]));

        $messages->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['system'] === 'guidance'
                    && $payload['messages'][0]['role'] === 'user'
                    && $payload['messages'][0]['content'][0]['text'] === 'Question';
            })
            ->andReturn($response);

        $provider = new AnthropicLlmProvider($client, $this->calculator());
        $request = new LlmRequest(
            model: 'claude-haiku-4-5',
            messages: [
                LlmMessage::system('guidance'),
                LlmMessage::user('Question'),
            ],
            stream: false,
            options: ['max_tokens' => 123],
        );

        $result = $provider->send($request);

        $this->assertSame('Answer', $result->collect());
        $this->assertSame(50, $result->usage()?->outputTokens);
        $expectedCost = 0.000001 * 100 + 0.000005 * 50;
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

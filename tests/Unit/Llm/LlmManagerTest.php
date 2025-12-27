<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\Contracts\LlmProvider;
use App\Services\Llm\LlmManager;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\LlmUsage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LlmManagerTest extends TestCase
{
    public function test_manager_dispatches_to_named_provider(): void
    {
        $request = new LlmRequest(model: 'demo', messages: []);
        $response = LlmResponse::fromText('ok', new LlmUsage());

        $provider = new class($response) implements LlmProvider {
            public function __construct(private readonly LlmResponse $response) {}

            public function send(LlmRequest $request): LlmResponse
            {
                return $this->response;
            }
        };

        $manager = new LlmManager(['demo' => $provider]);

        $this->assertSame($response, $manager->send('demo', $request));
        $this->assertSame(['demo'], $manager->providers());
    }

    public function test_missing_provider_throws(): void
    {
        $manager = new LlmManager([]);

        $this->expectException(InvalidArgumentException::class);
        $manager->send('missing', new LlmRequest('demo', []));
    }
}

<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\LlmProvider;
use InvalidArgumentException;

final class LlmManager
{
    /**
     * @param  array<string, LlmProvider>  $providers
     */
    public function __construct(private readonly array $providers)
    {
    }

    public function send(string $provider, LlmRequest $request): LlmResponse
    {
        return $this->provider($provider)->send($request);
    }

    public function provider(string $name): LlmProvider
    {
        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException(sprintf('LLM provider [%s] is not registered.', $name));
        }

        return $this->providers[$name];
    }

    /**
     * @return string[]
     */
    public function providers(): array
    {
        return array_keys($this->providers);
    }
}

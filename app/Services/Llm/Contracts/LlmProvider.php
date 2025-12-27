<?php

namespace App\Services\Llm\Contracts;

use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;

interface LlmProvider
{
    public function send(LlmRequest $request): LlmResponse;
}

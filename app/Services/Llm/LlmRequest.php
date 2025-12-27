<?php

namespace App\Services\Llm;

/**
 * @phpstan-type MessageList list<LlmMessage>
 */
final class LlmRequest
{
    public const INPUT_TYPE_TEXT = 'text';
    public const INPUT_TYPE_AUDIO = 'audio';

    /**
     * @param  MessageList  $messages
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly ?float $temperature = null,
        public readonly bool $stream = true,
        public readonly array $options = [],
        public readonly string $inputType = self::INPUT_TYPE_TEXT,
    ) {
    }

    /**
     * @return MessageList
     */
    public function conversationMessages(): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (LlmMessage $message): bool => $message->role !== LlmMessage::ROLE_SYSTEM,
        ));
    }

    public function systemMessage(): ?LlmMessage
    {
        foreach ($this->messages as $message) {
            if ($message->role === LlmMessage::ROLE_SYSTEM) {
                return $message;
            }
        }

        return null;
    }

    public function shouldStream(): bool
    {
        return $this->stream;
    }

    public function inputType(): string
    {
        return $this->inputType;
    }
}

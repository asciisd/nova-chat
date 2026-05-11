<?php

namespace Asciisd\NovaChat\Support;

use Asciisd\NovaChat\Contracts\Chattable;
use Asciisd\NovaChat\Contracts\ChatMessage;
use InvalidArgumentException;

class TopicDescriptor
{
    public function __construct(
        public readonly string $key,
        public readonly string $hostModel,
        public readonly string $messageModel,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly bool $default = false,
        public readonly mixed $query = null,
    ) {
        if (! is_subclass_of($this->hostModel, Chattable::class)) {
            throw new InvalidArgumentException(
                "Topic [{$key}] host model [{$hostModel}] must implement " . Chattable::class
            );
        }

        if (! is_subclass_of($this->messageModel, ChatMessage::class)) {
            throw new InvalidArgumentException(
                "Topic [{$key}] message model [{$messageModel}] must implement " . ChatMessage::class
            );
        }
    }

    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            hostModel: $config['model'] ?? throw new InvalidArgumentException("Topic [{$key}] missing 'model'"),
            messageModel: $config['message_model'] ?? throw new InvalidArgumentException("Topic [{$key}] missing 'message_model'"),
            label: $config['label'] ?? ucfirst($key),
            icon: $config['icon'] ?? null,
            default: (bool) ($config['default'] ?? false),
            query: $config['query'] ?? null,
        );
    }

    public function newHostQuery()
    {
        return ($this->hostModel)::query();
    }

    public function newMessageQuery()
    {
        return ($this->messageModel)::query();
    }
}

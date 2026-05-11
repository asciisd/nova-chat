<?php

namespace Asciisd\NovaChat\Concerns;

trait AsChatParticipant
{
    public function isChatAdmin(): bool
    {
        return false;
    }

    public function chatDisplayName(): string
    {
        return (string) ($this->name ?? $this->email ?? '#' . $this->getKey());
    }

    public function chatAvatarUrl(): ?string
    {
        return null;
    }
}

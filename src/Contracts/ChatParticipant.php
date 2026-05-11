<?php

namespace Asciisd\NovaChat\Contracts;

interface ChatParticipant
{
    public function isChatAdmin(): bool;

    public function chatDisplayName(): string;

    public function chatAvatarUrl(): ?string;
}

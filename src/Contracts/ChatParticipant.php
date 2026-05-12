<?php

namespace Asciisd\NovaChat\Contracts;

interface ChatParticipant
{
    public function isChatAdmin(): bool;

    public function chatDisplayName(): string;

    public function chatAvatarUrl(): ?string;

    /**
     * Whether this participant has been globally blocked from chat.
     *
     * The package's admin endpoint never inspects this — it only authors
     * messages on behalf of admins. Consumers MUST check this on their
     * user-side write endpoint to actually enforce the block.
     */
    public function isChatBlocked(): bool;
}

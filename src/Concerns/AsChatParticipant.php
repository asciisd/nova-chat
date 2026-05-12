<?php

namespace Asciisd\NovaChat\Concerns;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Asciisd\NovaChat\Support\BlockList;

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

    /**
     * Default block check: delegates to the package's BlockList service,
     * which reads the package-owned `nova_chat_blocked_participants` table.
     *
     * Override on a per-model basis only if you store the block status
     * elsewhere (e.g. a column on the user table or an external policy).
     */
    public function isChatBlocked(): bool
    {
        if (! $this instanceof ChatParticipant) {
            return false;
        }

        return app(BlockList::class)->isBlocked($this);
    }
}

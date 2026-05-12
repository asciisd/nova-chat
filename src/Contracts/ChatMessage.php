<?php

namespace Asciisd\NovaChat\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

interface ChatMessage
{
    public function chattable(): Relation;

    public function author(): MorphTo;

    public function getBody(): string;

    public function isRead(): bool;

    public function markAsRead(): void;

    public function isFromAdmin(): bool;

    /**
     * Soft-delete this message on behalf of an admin, recording the actor
     * and an optional reason.
     *
     * Implementations MUST throw if the model class does not use Laravel's
     * SoftDeletes trait — the package documents soft-delete as the only
     * supported moderation primitive.
     */
    public function deleteByAdmin(ChatParticipant $admin, ?string $reason = null): void;
}

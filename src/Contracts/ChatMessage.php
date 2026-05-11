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
}

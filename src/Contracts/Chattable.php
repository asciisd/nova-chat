<?php

namespace Asciisd\NovaChat\Contracts;

use Illuminate\Database\Eloquent\Relations\Relation;

interface Chattable
{
    public function chatMessages(): Relation;

    public function chatTitle(): string;

    public function chatSubtitle(): ?string;

    public function chatBadge(): ?string;
}

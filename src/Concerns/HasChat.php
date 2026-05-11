<?php

namespace Asciisd\NovaChat\Concerns;

trait HasChat
{
    public function chatTitle(): string
    {
        return $this->title
            ?? $this->name
            ?? $this->reference
            ?? '#' . $this->getKey();
    }

    public function chatSubtitle(): ?string
    {
        return null;
    }

    public function chatBadge(): ?string
    {
        return null;
    }
}

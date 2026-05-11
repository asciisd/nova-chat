<?php

namespace Asciisd\NovaChat\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

trait AsChatMessage
{
    public static function bootAsChatMessage(): void
    {
        static::creating(function ($message) {
            if (in_array('reference', $message->getFillable(), true) || array_key_exists('reference', $message->getAttributes())) {
                $message->reference ??= (string) Str::ulid();
            } elseif ($message->getConnection()->getSchemaBuilder()->hasColumn($message->getTable(), 'reference')) {
                $message->reference ??= (string) Str::ulid();
            }
        });
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function getBody(): string
    {
        return (string) $this->body;
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function markAsRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public function isFromAdmin(): bool
    {
        return (bool) $this->is_from_admin;
    }
}

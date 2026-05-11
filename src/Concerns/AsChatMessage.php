<?php

namespace Asciisd\NovaChat\Concerns;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

trait AsChatMessage
{
    public static function bootAsChatMessage(): void
    {
        static::creating(function ($message) {
            // Auto-fill ULID reference if the column exists and the developer didn't set one.
            if (in_array('reference', $message->getFillable(), true) || array_key_exists('reference', $message->getAttributes())) {
                $message->reference ??= (string) Str::ulid();
            } elseif ($message->getConnection()->getSchemaBuilder()->hasColumn($message->getTable(), 'reference')) {
                $message->reference ??= (string) Str::ulid();
            }

            // Auto-derive is_from_admin from the author when the developer didn't set it.
            // This keeps unread-badge queries cheap (the column is a stored bool) without
            // forcing every insertion site to remember `is_from_admin = $author->isChatAdmin()`.
            $message->resolveIsFromAdminIfMissing();
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

    /**
     * Populate is_from_admin from the author's ChatParticipant::isChatAdmin() if the
     * developer didn't explicitly include the attribute in the assignment.
     *
     * Order of precedence:
     *   1. Developer-provided value (anything passed to ->fill() / ->create()) — kept untouched.
     *   2. Author resolved via morphTo — used to derive the value.
     *   3. Column DB default (typically false).
     */
    protected function resolveIsFromAdminIfMissing(): void
    {
        if (array_key_exists('is_from_admin', $this->getAttributes())) {
            return;
        }

        if (! $this->author_type || ! $this->author_id) {
            return;
        }

        $author = $this->author;

        if ($author instanceof ChatParticipant) {
            $this->is_from_admin = $author->isChatAdmin();
        }
    }
}

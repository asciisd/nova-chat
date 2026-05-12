<?php

namespace Asciisd\NovaChat\Concerns;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;

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

    public function deletedBy(): MorphTo
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
     * Soft-delete the message on behalf of $admin, capturing audit metadata
     * (`deleted_by_*`, `deletion_reason`) before the SoftDeletes trait stamps
     * `deleted_at`.
     *
     * @throws LogicException when the model class does not use SoftDeletes.
     */
    public function deleteByAdmin(ChatParticipant $admin, ?string $reason = null): void
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            throw new LogicException(
                'Message model [' . static::class . '] must use ' . SoftDeletes::class
                . ' before it can be deleted via the chat moderation endpoint. '
                . 'Add `use Illuminate\\Database\\Eloquent\\SoftDeletes;` to the model '
                . 'and migrate a `deleted_at` column.'
            );
        }

        $attributes = [];

        if ($this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'deleted_by_type')) {
            $attributes['deleted_by_type'] = $admin instanceof \Illuminate\Database\Eloquent\Model
                ? $admin->getMorphClass()
                : $admin::class;
            $attributes['deleted_by_id'] = $admin instanceof \Illuminate\Database\Eloquent\Model
                ? $admin->getKey()
                : null;
        }

        if ($this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'deletion_reason')) {
            $attributes['deletion_reason'] = $reason;
        }

        if (! empty($attributes)) {
            $this->forceFill($attributes)->save();
        }

        $this->delete();
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

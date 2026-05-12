<?php

namespace Asciisd\NovaChat\Http\Resources;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Asciisd\NovaChat\Support\BlockList;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->resource->author;
        $isAdmin = $author instanceof ChatParticipant
            ? $author->isChatAdmin()
            : (bool) $this->resource->is_from_admin;

        $deletedAt = $this->resource->deleted_at ?? null;
        $deletedBy = $this->resource->relationLoaded('deletedBy')
            ? $this->resource->deletedBy
            : null;
        $deletedByType = $this->resource->deleted_by_type ?? null;
        $deletedById = $this->resource->deleted_by_id ?? null;

        return [
            'id' => $this->resource->getKey(),
            'reference' => $this->resource->reference ?? null,
            'body' => $this->resource->getBody(),
            'is_from_admin' => $isAdmin,
            'read_at' => optional($this->resource->read_at)?->toIso8601String(),
            'created_at' => optional($this->resource->created_at)?->toIso8601String(),
            'deleted_at' => optional($deletedAt)?->toIso8601String(),
            'deletion_reason' => $this->resource->deletion_reason ?? null,
            'deleted_by' => $deletedAt && $deletedByType ? [
                'type' => (string) $deletedByType,
                'id' => $deletedById,
                'name' => $deletedBy instanceof ChatParticipant
                    ? $deletedBy->chatDisplayName()
                    : ($deletedBy->name ?? '#' . $deletedById),
            ] : null,
            'author' => $author ? [
                'type' => $this->resource->author_type,
                'id' => $this->resource->author_id,
                'name' => $author instanceof ChatParticipant
                    ? $author->chatDisplayName()
                    : ($author->name ?? '#' . $author->getKey()),
                'avatar_url' => $author instanceof ChatParticipant
                    ? $author->chatAvatarUrl()
                    : null,
                'is_admin' => $isAdmin,
                'is_blocked' => $this->authorBlockedFlag($author, $isAdmin),
            ] : null,
        ];
    }

    /**
     * Compute author.is_blocked. Admin authors are never reported as blocked
     * (the block list applies to non-admin participants only). Falls back to
     * false when the author can't be resolved.
     */
    protected function authorBlockedFlag(mixed $author, bool $isAdmin): bool
    {
        if ($isAdmin || ! $author instanceof ChatParticipant) {
            return false;
        }

        return app(BlockList::class)->isBlocked($author);
    }
}

<?php

namespace Asciisd\NovaChat\Http\Resources;

use Asciisd\NovaChat\Contracts\ChatParticipant;
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

        return [
            'id' => $this->resource->getKey(),
            'reference' => $this->resource->reference ?? null,
            'body' => $this->resource->getBody(),
            'is_from_admin' => $isAdmin,
            'read_at' => optional($this->resource->read_at)?->toIso8601String(),
            'created_at' => optional($this->resource->created_at)?->toIso8601String(),
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
            ] : null,
        ];
    }
}

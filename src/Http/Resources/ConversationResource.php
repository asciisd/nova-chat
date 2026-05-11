<?php

namespace Asciisd\NovaChat\Http\Resources;

use Asciisd\NovaChat\Contracts\Chattable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Chattable $host */
        $host = $this->resource;
        $latest = $host->latest_chat_message ?? null;

        return [
            'id' => $host->getKey(),
            'reference' => $host->reference ?? null,
            'title' => $host->chatTitle(),
            'subtitle' => $host->chatSubtitle(),
            'badge' => $host->chatBadge(),
            'unread_count' => (int) ($host->unread_chat_count ?? 0),
            'latest_message' => $latest ? [
                'id' => $latest->getKey(),
                'body_excerpt' => Str::limit((string) $latest->body, 80),
                'created_at' => optional($latest->created_at)?->toIso8601String(),
                'is_from_admin' => (bool) $latest->is_from_admin,
            ] : null,
        ];
    }
}

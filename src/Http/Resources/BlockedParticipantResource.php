<?php

namespace Asciisd\NovaChat\Http\Resources;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockedParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->resource->participant;
        $blocker = $this->resource->blockedBy;

        return [
            'id' => $this->resource->getKey(),
            'participant' => $this->serializeParticipant(
                $participant,
                (string) $this->resource->participant_type,
                $this->resource->participant_id,
            ),
            'blocked_by' => $this->resource->blocked_by_type
                ? $this->serializeParticipant(
                    $blocker,
                    (string) $this->resource->blocked_by_type,
                    $this->resource->blocked_by_id,
                )
                : null,
            'reason' => $this->resource->reason,
            'created_at' => optional($this->resource->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $model  The resolved morphTo target, or null when the row
     *                        points at a deleted/missing record.
     */
    protected function serializeParticipant(mixed $model, string $type, mixed $id): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $model instanceof ChatParticipant
                ? $model->chatDisplayName()
                : ($model->name ?? '#' . $id),
            'avatar_url' => $model instanceof ChatParticipant
                ? $model->chatAvatarUrl()
                : null,
        ];
    }
}

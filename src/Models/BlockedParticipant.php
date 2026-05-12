<?php

namespace Asciisd\NovaChat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent record for a globally-blocked chat participant.
 *
 * Owned by the package (lives in the package's auto-loaded migration). Apps
 * should not extend this — interact via {@see \Asciisd\NovaChat\Support\BlockList}.
 */
class BlockedParticipant extends Model
{
    protected $table = 'nova_chat_blocked_participants';

    protected $fillable = [
        'participant_type',
        'participant_id',
        'blocked_by_type',
        'blocked_by_id',
        'reason',
    ];

    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    public function blockedBy(): MorphTo
    {
        return $this->morphTo();
    }
}

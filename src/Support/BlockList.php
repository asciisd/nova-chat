<?php

namespace Asciisd\NovaChat\Support;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Asciisd\NovaChat\Models\BlockedParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Authoritative store for global chat blocks.
 *
 * The package's controllers and the AsChatParticipant trait both call into
 * this service so consumers can swap the storage backend (cache, custom
 * table, external service) without rewriting the controllers.
 *
 * A per-request memoization layer keeps {@see isBlocked()} cheap during a
 * single request — the unread-badge polling cadence assumes O(1) lookups.
 */
class BlockList
{
    /**
     * @var array<string, bool> map of "{type}:{id}" => is-blocked
     */
    protected array $cache = [];

    public function isBlocked(ChatParticipant $participant): bool
    {
        $key = $this->cacheKey($participant);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = BlockedParticipant::query()
            ->where('participant_type', $this->morphAlias($participant))
            ->where('participant_id', $this->participantKey($participant))
            ->exists();
    }

    public function block(
        ChatParticipant $participant,
        ?ChatParticipant $blocker = null,
        ?string $reason = null,
    ): BlockedParticipant {
        $record = BlockedParticipant::query()->updateOrCreate(
            [
                'participant_type' => $this->morphAlias($participant),
                'participant_id' => $this->participantKey($participant),
            ],
            [
                'blocked_by_type' => $blocker ? $this->morphAlias($blocker) : null,
                'blocked_by_id' => $blocker ? $this->participantKey($blocker) : null,
                'reason' => $reason,
            ],
        );

        $this->cache[$this->cacheKey($participant)] = true;

        return $record;
    }

    public function unblock(ChatParticipant $participant): bool
    {
        $deleted = BlockedParticipant::query()
            ->where('participant_type', $this->morphAlias($participant))
            ->where('participant_id', $this->participantKey($participant))
            ->delete() > 0;

        $this->cache[$this->cacheKey($participant)] = false;

        return $deleted;
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return BlockedParticipant::query()
            ->with(['participant', 'blockedBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Forget the in-memory cache. Useful in long-running workers where a
     * single process serves many requests.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    protected function morphAlias(ChatParticipant $participant): string
    {
        if (! $participant instanceof Model) {
            // Defensive: ChatParticipant is an interface; in practice every
            // implementer is also an Eloquent model. Fall back to FQCN.
            return $participant::class;
        }

        return $participant->getMorphClass();
    }

    protected function participantKey(ChatParticipant $participant): int|string
    {
        if ($participant instanceof Model) {
            return $participant->getKey();
        }

        return method_exists($participant, 'getKey') ? $participant->getKey() : 0;
    }

    protected function cacheKey(ChatParticipant $participant): string
    {
        return $this->morphAlias($participant) . ':' . $this->participantKey($participant);
    }
}

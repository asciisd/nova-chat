<?php

namespace Asciisd\NovaChat\Http\Controllers;

use Asciisd\NovaChat\Contracts\ChatParticipant;
use Asciisd\NovaChat\Http\Resources\BlockedParticipantResource;
use Asciisd\NovaChat\Support\BlockList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BlockedParticipantsController extends Controller
{
    public function __construct(protected BlockList $blocks) {}

    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $perPage = (int) min(100, max(5, (int) $request->query('per_page', 25)));

        return BlockedParticipantResource::collection(
            $this->blocks->paginate($perPage)
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $maxReason = (int) config('nova-chat.moderation.max_reason_length', 500);

        $data = $request->validate([
            'participant_type' => ['required', 'string'],
            'participant_id' => ['required'],
            'reason' => ['nullable', 'string', "max:{$maxReason}"],
        ]);

        $participant = $this->resolveParticipant($data['participant_type'], $data['participant_id']);

        $admin = $this->currentAdmin();

        $record = $this->blocks->block(
            $participant,
            $admin,
            $data['reason'] ?? null,
        );

        $record->load(['participant', 'blockedBy']);

        return (new BlockedParticipantResource($record))->response()->setStatusCode(201);
    }

    public function destroy(string $type, string $id): JsonResponse
    {
        $this->ensureFeatureEnabled();

        $participant = $this->resolveParticipant($type, $id);

        $this->blocks->unblock($participant);

        return response()->json(null, 204);
    }

    protected function ensureFeatureEnabled(): void
    {
        if (! (bool) config('nova-chat.moderation.allow_block', true)) {
            abort(403, 'Chat blocking is disabled in this installation.');
        }
    }

    /**
     * Resolve a polymorphic identifier (alias from morph_map + key) into a
     * ChatParticipant model instance.
     */
    protected function resolveParticipant(string $type, mixed $id): ChatParticipant
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class)) {
            abort(422, "Unknown participant type [{$type}].");
        }

        $instance = (new $class)->newQuery()->whereKey($id)->first();

        if (! $instance instanceof Model) {
            abort(404, 'Participant not found.');
        }

        if (! $instance instanceof ChatParticipant) {
            abort(422, "Model [{$class}] must implement " . ChatParticipant::class);
        }

        return $instance;
    }

    protected function currentAdmin(): ?ChatParticipant
    {
        $guard = config('nova-chat.admin_guard', 'admin');
        $user = Auth::guard($guard)->user();

        return $user instanceof ChatParticipant ? $user : null;
    }
}

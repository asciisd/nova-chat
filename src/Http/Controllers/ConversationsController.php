<?php

namespace Asciisd\NovaChat\Http\Controllers;

use Asciisd\NovaChat\Contracts\ChatMessage;
use Asciisd\NovaChat\Contracts\ChatParticipant;
use Asciisd\NovaChat\Http\Resources\ConversationResource;
use Asciisd\NovaChat\Http\Resources\MessageResource;
use Asciisd\NovaChat\Support\TopicDescriptor;
use Asciisd\NovaChat\Support\TopicRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LogicException;

class ConversationsController extends Controller
{
    public function __construct(protected TopicRegistry $topics) {}

    public function topics(): JsonResponse
    {
        $items = $this->topics->collection()->map(function (TopicDescriptor $topic) {
            return [
                'key' => $topic->key,
                'label' => $topic->label,
                'icon' => $topic->icon,
                'default' => $topic->default,
                'unread_count' => $this->unreadCountFor($topic),
            ];
        })->all();

        return response()->json([
            'data' => $items,
            'config' => config('nova-chat.poll_interval_ms', ['sidebar' => 4000, 'thread' => 3000]),
            'moderation' => [
                'allow_block' => (bool) config('nova-chat.moderation.allow_block', true),
                'allow_delete' => (bool) config('nova-chat.moderation.allow_delete', true),
            ],
        ]);
    }

    public function index(Request $request, string $topic): JsonResponse
    {
        $descriptor = $this->topics->find($topic);
        $hostInstance = new ($descriptor->hostModel);
        $messageInstance = new ($descriptor->messageModel);

        $hostTable = $hostInstance->getTable();
        $hostKey = $hostInstance->getKeyName();
        $messageTable = $messageInstance->getTable();
        $foreignKey = $this->hostForeignKey($descriptor);

        $query = $descriptor->newHostQuery();

        if (is_callable($descriptor->query)) {
            $query = call_user_func($descriptor->query, $query) ?? $query;
        }

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $query->where(function ($q) use ($search, $hostTable) {
                $q->where("{$hostTable}.title", 'like', "%{$search}%")
                    ->orWhere("{$hostTable}.reference", 'like', "%{$search}%");
            });
        }

        $latestAtSub = ($descriptor->messageModel)::query()
            ->selectRaw('max(created_at)')
            ->whereColumn("{$messageTable}.{$foreignKey}", "{$hostTable}.{$hostKey}");

        $query
            ->select("{$hostTable}.*")
            ->selectSub($latestAtSub, 'latest_message_at')
            ->withCount(['chatMessages as unread_chat_count' => function ($q) {
                $q->where('is_from_admin', false)->whereNull('read_at');
            }])
            ->orderByRaw('latest_message_at IS NULL, latest_message_at DESC')
            ->orderByDesc("{$hostTable}.updated_at");

        $perPage = (int) min(50, max(5, (int) $request->query('per_page', 25)));
        $paginated = $query->paginate($perPage);

        $this->hydrateLatestMessages($paginated->getCollection(), $descriptor, $foreignKey);

        return ConversationResource::collection($paginated)->response();
    }

    public function messages(Request $request, string $topic, int|string $id): JsonResponse
    {
        $descriptor = $this->topics->find($topic);
        $host = $this->findHost($descriptor, $id);

        $query = $host->chatMessages()->with(['author', 'deletedBy'])->orderBy('id');

        // Admins see soft-deleted messages (grayed-out + reason) so moderation
        // is auditable. The user-side endpoint — which is the consumer's own
        // route — naturally hides them via the SoftDeletes global scope.
        if ($this->messageModelUsesSoftDeletes($descriptor)) {
            $query->withTrashed();
        }

        if ($after = $request->query('after')) {
            $query->where('id', '>', (int) $after);
        }

        $perPage = (int) min(200, max(20, (int) $request->query('per_page', 100)));
        $messages = $query->paginate($perPage);

        return MessageResource::collection($messages)->response();
    }

    public function store(Request $request, string $topic, int|string $id): JsonResponse
    {
        $descriptor = $this->topics->find($topic);
        $host = $this->findHost($descriptor, $id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message body cannot be empty.']);
        }

        $admin = $this->currentAdmin();

        $message = $host->chatMessages()->create([
            'body' => $body,
            'author_type' => $admin->getMorphClass(),
            'author_id' => $admin->getKey(),
            'is_from_admin' => true,
        ]);

        $message->setRelation('author', $admin);

        return (new MessageResource($message))->response();
    }

    public function destroy(Request $request, string $topic, int|string $id, int|string $messageId): JsonResponse
    {
        if (! (bool) config('nova-chat.moderation.allow_delete', true)) {
            abort(403, 'Chat message deletion is disabled in this installation.');
        }

        $descriptor = $this->topics->find($topic);
        $host = $this->findHost($descriptor, $id);

        if (! $this->messageModelUsesSoftDeletes($descriptor)) {
            abort(422, 'Message model [' . $descriptor->messageModel . '] must use '
                . SoftDeletes::class
                . ' before messages can be deleted. Add `use SoftDeletes;` to the model and migrate a `deleted_at` column.');
        }

        $maxReason = (int) config('nova-chat.moderation.max_reason_length', 500);

        $data = $request->validate([
            'reason' => ['nullable', 'string', "max:{$maxReason}"],
        ]);

        $message = $host->chatMessages()->whereKey($messageId)->first();

        if (! $message instanceof ChatMessage) {
            abort(404, 'Message not found in this conversation.');
        }

        $admin = $this->currentAdmin();

        try {
            $message->deleteByAdmin($admin, $data['reason'] ?? null);
        } catch (LogicException $e) {
            // Defensive: messageModelUsesSoftDeletes() already checked, but
            // surface the trait's error verbatim if the contract impl disagrees.
            abort(422, $e->getMessage());
        }

        return response()->json(null, 204);
    }

    public function read(string $topic, int|string $id): JsonResponse
    {
        $descriptor = $this->topics->find($topic);
        $host = $this->findHost($descriptor, $id);

        $count = $host->chatMessages()
            ->where('is_from_admin', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked_read' => $count]);
    }

    protected function findHost(TopicDescriptor $descriptor, int|string $id): Model
    {
        $host = $descriptor->newHostQuery()->whereKey($id)->first();

        if (! $host) {
            abort(404, 'Conversation not found.');
        }

        return $host;
    }

    protected function unreadCountFor(TopicDescriptor $topic): int
    {
        return (int) $topic->newMessageQuery()
            ->where('is_from_admin', false)
            ->whereNull('read_at')
            ->count();
    }

    protected function hostForeignKey(TopicDescriptor $topic): string
    {
        $relation = (new ($topic->hostModel))->chatMessages();
        $key = $relation->getForeignKeyName();

        return str_contains($key, '.') ? array_slice(explode('.', $key), -1)[0] : $key;
    }

    protected function messageModelUsesSoftDeletes(TopicDescriptor $descriptor): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($descriptor->messageModel),
            true,
        );
    }

    protected function hydrateLatestMessages($hosts, TopicDescriptor $descriptor, string $foreignKey): void
    {
        $ids = $hosts->modelKeys();

        if (empty($ids)) {
            return;
        }

        $latestIds = ($descriptor->messageModel)::query()
            ->selectRaw('max(id) as id')
            ->whereIn($foreignKey, $ids)
            ->groupBy($foreignKey)
            ->pluck('id');

        $messages = ($descriptor->messageModel)::query()
            ->whereIn('id', $latestIds)
            ->get()
            ->keyBy($foreignKey);

        $hosts->each(function ($host) use ($messages) {
            $host->setRelation('latest_chat_message', $messages->get($host->getKey()));
        });
    }

    protected function currentAdmin(): ChatParticipant
    {
        $guard = config('nova-chat.admin_guard', 'admin');
        $user = Auth::guard($guard)->user();

        if (! $user) {
            abort(401, 'Unauthenticated chat admin.');
        }

        if (! $user instanceof ChatParticipant) {
            abort(500, 'Admin model must implement ' . ChatParticipant::class);
        }

        return $user;
    }
}

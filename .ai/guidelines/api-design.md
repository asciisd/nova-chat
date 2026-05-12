# API design (always-on)

There is exactly **one** controller — `ConversationsController` — and five
routes. Every change to the wire format affects every consumer.
Treat this surface as a public API.

## The route table

```
GET    /nova-vendor/nova-chat/topics
GET    /nova-vendor/nova-chat/topics/{topic}/conversations?search=&page=&per_page=
GET    /nova-vendor/nova-chat/topics/{topic}/conversations/{id}/messages?after=<lastId>&per_page=
POST   /nova-vendor/nova-chat/topics/{topic}/conversations/{id}/messages         { body }
DELETE /nova-vendor/nova-chat/topics/{topic}/conversations/{id}/messages/{msg}   { reason? }
POST   /nova-vendor/nova-chat/topics/{topic}/conversations/{id}/read

GET    /nova-vendor/nova-chat/blocks?per_page=
POST   /nova-vendor/nova-chat/blocks                                              { participant_type, participant_id, reason? }
DELETE /nova-vendor/nova-chat/blocks/{participant_type}/{participant_id}
```

All routes sit behind Nova's `api_middleware` group, which resolves the
guard from `config('nova-chat.admin_guard')` → `config('nova.guard')` →
`'admin'`. Never bypass that middleware in `routes/api.php`.

## Response envelopes (don't break these)

```
GET /topics
→ {
    data: [{ key, label, icon, default, unread_count }],
    config: { sidebar: <ms>, thread: <ms> },
    moderation: { allow_block: bool, allow_delete: bool }
  }

GET /topics/{topic}/conversations
→ paginated [{
    id, reference, title, subtitle, badge, unread_count,
    latest_message: { id, body, created_at, is_from_admin }
  }]

GET /topics/{topic}/conversations/{id}/messages
→ paginated [{
    id, reference, body, is_from_admin, read_at, created_at,
    deleted_at, deletion_reason,
    deleted_by: { type, id, name } | null,
    author: { type, id, name, avatar_url, is_admin, is_blocked }
  }]
   (admin context — uses withTrashed(); soft-deleted rows are visible)

POST /topics/{topic}/conversations/{id}/messages
→ { data: <same shape as a GET message> }

DELETE /topics/{topic}/conversations/{id}/messages/{message}
→ 204; returns 422 if the message model doesn't use SoftDeletes

POST /topics/{topic}/conversations/{id}/read
→ { marked_read: <int> }

GET /blocks
→ paginated [{ id, participant: {...}, blocked_by: {...}|null, reason, created_at }]

POST /blocks
→ 201 { data: <BlockedParticipant resource> }

DELETE /blocks/{type}/{id}
→ 204
```

`author.type` is the **morph alias** from `config('nova-chat.morph_map')`
(e.g. `'admin'`, `'user'`), not the FQCN. This is what makes the wire
format refactor-safe.

## Request validation

`POST /messages` validates `body` as `required|string|max:5000`. The
controller trims it before `create()`. Don't relax these without a
deprecation plan — apps may depend on the 5000-char ceiling.

## Pagination

- Default `per_page = 20` for conversations, `50` for messages.
- Cap at 100 to keep poll responses small.
- Keep using Laravel's standard paginator envelope (`data`, `meta`,
  `links`) — Vue depends on `meta.has_more_pages` for infinite scroll.

## Adding a new endpoint

1. Add the route to `routes/api.php` — keep the `/topics/{topic}/…`
   prefix style. Topic-less endpoints are a smell; the registry should
   resolve a topic for almost everything.
2. Add a controller method on `ConversationsController` (or split when
   it grows past ~10 methods — not before).
3. Resolve the topic with `$this->topicRegistry->find($topic)` and let
   it throw a 404 if the topic key is unknown.
4. Return through a JSON resource in `src/Http/Resources/`. Inline
   array shaping in the controller is forbidden.
5. Update `resources/boost/skills/nova-chat-development/SKILL.md` →
   "API contract (for AI-generated client code)" so consumers and
   their AIs stay in sync.

## What `is_from_admin` means on the wire

It's a stored boolean on the message row (set at insert time, never
recomputed). The controller passes it through verbatim. The Vue UI
right-aligns when `is_from_admin === true` and left-aligns otherwise.
There is no third state.

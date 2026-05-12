# Package architecture (always-on)

You are working **inside** `asciisd/nova-chat` — a Laravel Nova tool, not a
host application. Keep this map in mind before editing any file.

## Top-level layout

```
src/
├── NovaChat.php                  Tool subclass — registers dist/js/tool.{js,css}
├── NovaChatServiceProvider.php   Auto-discovered SP; mounts routes + morph map
├── Concerns/
│   ├── HasChat.php               trait for Chattable host models
│   ├── AsChatMessage.php         trait for ChatMessage models (auto is_from_admin, ulid)
│   └── AsChatParticipant.php     trait for ChatParticipant author models
├── Contracts/
│   ├── Chattable.php             host model interface
│   ├── ChatMessage.php           message model interface
│   └── ChatParticipant.php       author model interface
├── Http/
│   ├── Controllers/              ConversationsController only
│   └── Resources/                JSON API resources (the wire format)
└── Support/
    ├── TopicRegistry.php         resolves config('nova-chat.topics') into descriptors
    └── TopicDescriptor.php       value object: key, host class, message class, label, …

routes/
├── api.php                       /nova-vendor/nova-chat/* JSON endpoints
└── inertia.php                   /nova/nova-chat Inertia page

resources/
├── js/
│   ├── pages/Tool.vue            entry component registered as 'NovaChat'
│   ├── components/
│   │   ├── TopicTabs.vue
│   │   ├── ConversationList.vue  sidebar, polls every 4 s
│   │   ├── ConversationPane.vue  thread + composer, polls every 3 s (delta-only)
│   │   ├── MessageBubble.vue
│   │   └── MessageComposer.vue
│   ├── lib/time.js
│   └── tool.js                   Vite entry; calls Nova.booting(...)
├── css/                          empty placeholder
└── boost/                        Laravel Boost guidelines + skills published to consumers

config/nova-chat.php              shipped defaults
database/stubs/                   chat_messages_table.stub (publishable)
dist/                             COMPILED Vue bundle — committed; loaded by NovaChat::boot()
```

## Request flow (admin opens the tool)

1. Browser hits `GET /nova/nova-chat` → Inertia renders the `NovaChat` page
   from `routes/inertia.php`.
2. `Tool.vue` calls `GET /nova-vendor/nova-chat/topics` →
   `ConversationsController::topics()` → `TopicRegistry` reads
   `config('nova-chat.topics')` → returns `{ data, config: { sidebar, thread } }`.
3. `TopicTabs` shows one tab per topic. `ConversationList` polls
   `GET /topics/{topic}/conversations` every `sidebar` ms.
4. Selecting a row mounts `ConversationPane`, which polls
   `GET /topics/{topic}/conversations/{id}/messages?after=<lastId>` every
   `thread` ms (delta-only — only IDs newer than the last seen one).
5. Sending a reply: `POST /topics/{topic}/conversations/{id}/messages` —
   the controller fills `is_from_admin = true` because the request is
   authenticated under the admin guard.
6. Marking read: `POST /topics/{topic}/conversations/{id}/read` —
   updates `read_at` on every unread message authored by **non-admins**.

## Two service-provider responsibilities

`NovaChatServiceProvider::boot()`:

1. Publishes `config/nova-chat.php` (tag `nova-chat-config`) and
   `database/stubs/` (tag `nova-chat-stubs`).
2. Calls `Relation::enforceMorphMap(config('nova-chat.morph_map'))` —
   this is what stops `author_type` storing full FQCNs.
3. Mounts `routes/api.php` under `nova-vendor/nova-chat` with Nova's
   `api_middleware` group, and `routes/inertia.php` under Nova's router.

If you add a new responsibility, put it here — never in `NovaChat.php`
(that class only registers assets and the menu).

## What the package never does

- It never references an app-namespaced class (no `App\Models\Signal`,
  no `App\Models\Admin`). Every contact point is a contract.
- It never owns a `chat_messages` table. Each consuming topic supplies its
  own table; the package only documents the required columns.
- It never broadcasts. The thread refreshes via short-poll only — adding
  Reverb/Pusher is a v2 conversation, not a small change.
- It never authenticates users itself — it relies entirely on Nova's
  configured `admin_guard`.

If a feature request would force any of these, push back before
implementing — see `.ai/guidelines/contract-purity.md`.

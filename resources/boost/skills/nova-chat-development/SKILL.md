---
name: nova-chat-development
description: Add or extend WhatsApp-style chat threads inside Laravel Nova using asciisd/nova-chat — wire a new Chattable host model, create its message table + model, register the topic, and troubleshoot the most common integration issues.
---

# Nova Chat Development

## When to use this skill

Invoke this skill in a project that has `asciisd/nova-chat` installed (check `composer.json`) when the user wants to:

- Add chat / messaging / threaded comments to a Nova-managed model (Signal, Ticket, Order, Project, …).
- Register an additional topic so the chat sidebar gains a new tab.
- Wire a model class to satisfy the package's contracts.
- Build the required message table — including the non-obvious `is_from_admin` column and composite indexes.
- Connect Admin / User / other participant models to the `ChatParticipant` contract.
- Diagnose: tool button returns 404, sidebar shows zero topics, unread badge never clears, `author_type` stores full class names, polling never updates the UI, or `Call to undefined method` on a participant after signing into Nova.

Do **not** invoke when:

- The project doesn't have `asciisd/nova-chat` in `composer.json` — propose the right alternative instead.
- The user wants real-time WebSocket delivery (the package is polling-only by design in v1).
- The user wants user↔user DMs unrelated to any host record — the package is host-record-centric.

## Architecture at a glance

```
Nova sidebar "Chat"
   └─ /nova/nova-chat  (Inertia page → registered as 'NovaChat')
        ├─ TopicTabs       (one tab per registered topic)
        ├─ ConversationList (sidebar, polls every 4 s)
        └─ ConversationPane (thread + composer, polls every 3 s, delta only)
                ▼ axios
   /nova-vendor/nova-chat/topics/{topic}/…
        ▼
   ConversationsController → TopicRegistry → config('nova-chat.topics')
        ▼
   For each topic, the developer plugs in:
     host model     implements Chattable        (use HasChat)
     message model  implements ChatMessage      (use AsChatMessage)
     author models  implement  ChatParticipant  (use AsChatParticipant)
```

The package's `src/` never imports any app-namespaced class. Treat the three contracts as the only contact surface.

## The six-step playbook (adding a new Chattable)

The user says "let me chat about Orders inside Nova." Walk through these six steps. Replace `Order`, `OrderMessage`, `orders`, `order_messages`, `order_id` with the user's actual host.

### Step 1 — Create the message table migration

```php
// database/migrations/2026_xx_xx_create_order_messages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('reference')->unique();            // recommended, not required
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->morphs('author');                       // author_type + author_id
            $table->text('body');
            $table->json('attachments')->nullable();        // recommended forward-compat
            $table->boolean('is_from_admin')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['order_id', 'is_from_admin', 'read_at'], 'order_messages_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
```

**Why `is_from_admin` is a stored column (not derived per-query):** the sidebar's unread badge runs a hot query (`where('is_from_admin', false)->whereNull('read_at')->count()`). A polymorphic `whereHasMorph(...)` join is roughly 10× more expensive at scale. The denormalized column + composite index lets unread counts stay O(log n).

**You don't manually set `is_from_admin` at insertion time** — the `AsChatMessage` trait fills it from `$author->isChatAdmin()` on the `creating` event whenever the developer didn't include the attribute in the assignment. An explicit value (e.g. `is_from_admin => true` in `->create([...])`) is always respected. Cost: one extra `SELECT` against the author table per insert.

If you want a one-shot template, publish the package stub instead:

```bash
php artisan vendor:publish --tag=nova-chat-stubs
# → database/stubs/nova-chat/chat_messages_table.stub
```

### Step 2 — Create the message model

```php
namespace App\Models;

use Asciisd\NovaChat\Concerns\AsChatMessage;
use Asciisd\NovaChat\Contracts\ChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMessage extends Model implements ChatMessage
{
    use AsChatMessage;

    protected $fillable = [
        'order_id', 'reference', 'author_type', 'author_id',
        'body', 'attachments', 'is_from_admin', 'read_at',
    ];

    protected $casts = [
        'attachments'   => 'array',
        'is_from_admin' => 'bool',
        'read_at'       => 'datetime',
    ];

    public function chattable(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
```

The `AsChatMessage` trait provides `author()` (morphTo), `getBody()`, `isRead()`, `markAsRead()`, `isFromAdmin()`, and auto-fills `reference` with a ULID on `creating` if the column exists. **You only need to define `chattable()`** — name and FK column are project-specific so the trait can't infer them.

### Step 3 — Make the host model Chattable

```php
namespace App\Models;

use Asciisd\NovaChat\Concerns\HasChat;
use Asciisd\NovaChat\Contracts\Chattable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model implements Chattable
{
    use HasChat;

    public function chatMessages(): HasMany
    {
        return $this->hasMany(OrderMessage::class);
    }

    public function chatTitle(): string     { return "Order #{$this->number}"; }
    public function chatSubtitle(): ?string { return $this->customer_name; }
    public function chatBadge(): ?string    { return $this->status?->value; }
}
```

`HasChat` provides default `chatTitle()/chatSubtitle()/chatBadge()` (title fallback chain, both subtitle and badge return null). Override per-topic for a richer sidebar row.

### Step 4 — Make participant models implement `ChatParticipant`

Admin (the one signing into Nova) must return `true` from `isChatAdmin()`:

```php
namespace App\Models;

use Asciisd\NovaChat\Concerns\AsChatParticipant;
use Asciisd\NovaChat\Contracts\ChatParticipant;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable implements ChatParticipant
{
    use AsChatParticipant;

    public function isChatAdmin(): bool
    {
        return true;
    }
}
```

End users default to `false`:

```php
namespace App\Models;

use Asciisd\NovaChat\Concerns\AsChatParticipant;
use Asciisd\NovaChat\Contracts\ChatParticipant;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements ChatParticipant
{
    use AsChatParticipant; // isChatAdmin() inherits → false
}
```

`AsChatParticipant` provides `chatDisplayName()` (returns `name` then `email` then `#id`) and `chatAvatarUrl()` (null). Override either if the project needs Gravatar URLs, etc.

### Step 5 — Register the topic + morph map in `config/nova-chat.php`

```php
return [
    'admin_guard' => env('NOVA_CHAT_ADMIN_GUARD', config('nova.guard') ?: 'admin'),

    'morph_map' => [
        'admin' => \App\Models\Admin::class,
        'user'  => \App\Models\User::class,
        'order' => \App\Models\Order::class,
        // add an entry for every author class AND every host class
    ],

    'topics' => [
        'order' => [
            'model'         => \App\Models\Order::class,        // implements Chattable
            'message_model' => \App\Models\OrderMessage::class, // implements ChatMessage
            'label'         => 'Orders',
            'icon'          => 'shopping-bag',                  // Heroicons name
            'default'       => true,                            // exactly one default
        ],
    ],

    'poll_interval_ms' => [
        'sidebar' => 4000,
        'thread'  => 3000,
    ],
];
```

For multiple topics, add more entries to `topics`. The Vue UI renders a tab switcher automatically when `topics.length > 1`. Only one topic should carry `'default' => true`.

### Step 6 — Verify

```bash
php artisan migrate
php artisan optimize:clear      # if you changed config/routes
php artisan route:list | grep nova-chat
```

Expected routes:

```
GET     nova/nova-chat                                                  (Inertia tool page)
GET     nova-vendor/nova-chat/topics
GET     nova-vendor/nova-chat/topics/{topic}/conversations
GET     nova-vendor/nova-chat/topics/{topic}/conversations/{id}/messages
POST    nova-vendor/nova-chat/topics/{topic}/conversations/{id}/messages
POST    nova-vendor/nova-chat/topics/{topic}/conversations/{id}/read
```

Quick controller smoke test in tinker:

```php
use Asciisd\NovaChat\Http\Controllers\ConversationsController;
use Illuminate\Support\Facades\Auth;

Auth::guard('admin')->login(\App\Models\Admin::first());
echo app(ConversationsController::class)->topics()->getContent();
```

You should see your topic with a numeric `unread_count`.

## Troubleshooting cheatsheet

| Symptom | Cause | Fix |
|---------|-------|-----|
| Clicking **Chat** in the sidebar returns 404 | The Inertia page route at `/nova/nova-chat` isn't registered | Verify the package's `NovaChatServiceProvider` is auto-discovered (look in `bootstrap/cache/packages.php`). Run `php artisan optimize:clear`. |
| Sidebar loads but shows "No chat topics are configured" | `config('nova-chat.topics')` is empty | Publish + populate the config (`php artisan vendor:publish --tag=nova-chat-config`). |
| `Call to undefined method App\Models\Admin::toUserTeams()` after Nova login | App's `HandleInertiaRequests` calls `User`-only methods on the now-`Admin` user | Guard the share closures with `method_exists($user, 'toUserTeams')`. |
| `author_type` rows contain full class strings like `App\Models\Admin` | Morph map missing the alias | Add `'admin' => \App\Models\Admin::class` etc. to `config('nova-chat.morph_map')` and re-seed. Existing rows must be backfilled manually. |
| `InvalidArgumentException: Topic [x] missing 'message_model'` | Topic config has `model` but no `message_model` | Each topic needs both keys — the package no longer ships a default shared table. |
| Admin replies don't show right-aligned in the UI | Either `is_from_admin` not set on insert, or the admin model's `isChatAdmin()` returns false | The controller sets `is_from_admin = true` automatically for admin-authored messages — only an issue if you create messages outside the controller. Confirm Admin overrides `isChatAdmin(): bool { return true; }`. |
| Unread badge never clears even after viewing a thread | The `read` endpoint requires the admin guard | Confirm `config('nova-chat.admin_guard')` matches the guard the user is signed in with. In most projects this defaults correctly from `nova.guard`. |
| Vue changes don't appear after editing the package | Bundle not rebuilt | `cd vendor/asciisd/nova-chat && npm run build`. The consuming app's Vite pipeline is unrelated. |
| Sidebar order doesn't refresh when new messages arrive | Polling is paused by browser | Polling pauses when `document.visibilityState === 'hidden'`. Polling resumes on tab focus. |

## API contract (for AI-generated client code)

All endpoints sit under `/nova-vendor/nova-chat/` and require the configured admin guard. Use `Nova.request()` inside the Vue UI (already-configured axios instance) or normal axios with CSRF cookie outside.

```
GET    /topics
       → { data: [{ key, label, icon, default, unread_count }], config: { sidebar, thread } }

GET    /topics/{topic}/conversations?search=&page=&per_page=
       → paginated [{ id, reference, title, subtitle, badge, unread_count, latest_message: {...} }]

GET    /topics/{topic}/conversations/{id}/messages?after=<lastId>&per_page=
       → paginated [{ id, reference, body, is_from_admin, read_at, created_at, author: { type, id, name, avatar_url, is_admin } }]

POST   /topics/{topic}/conversations/{id}/messages
       body: { body: string (required, max:5000, trimmed) }
       → { data: { ...same shape as GET message... } }

POST   /topics/{topic}/conversations/{id}/read
       → { marked_read: number }
```

`type` in `author` is the morph alias from `morph_map` — short, refactor-safe (`'admin'`, `'user'`).

## Anti-patterns to refuse

If the user asks for any of the following inside a project that has this package, push back before implementing:

- "Make all topics share one `chat_messages` table." → Each topic gets its own table by design. Sharing collapses the polymorphic FK to a chattable_type column the controller doesn't read.
- "Compute `is_from_admin` from the morph type at query time." → That's exactly what the column is denormalized to avoid. Leave it as a stored bool.
- "Reference `Signal` (or any concrete host) from inside `vendor/asciisd/nova-chat/`." → Contract violation. The package is generic on purpose; the contracts are the only contact surface.
- "Add a `recipient_id` to messages so threads are 1:1." → The schema is intentionally group-shaped (one thread per host, polymorphic authors). 1:1 DM-style messaging is a different domain — build a separate package.
- "Use Reverb / Pusher for real-time updates." → v1 is polling-only on purpose. Adding broadcasting is a roadmap item, not a small change — confirm with the user before they commit time.

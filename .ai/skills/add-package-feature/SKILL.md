---
name: add-package-feature
description: Add a new feature to the asciisd/nova-chat package itself (new contract method, controller endpoint, JSON resource field, or config knob) without breaking the contract-driven design or the consumer-facing wire format.
---

# Add a feature to `asciisd/nova-chat`

## When to use this skill

Invoke when the user wants to **extend the package** — not when they
want to integrate it into an app (that's `resources/boost/skills/nova-chat-development/`).

Typical triggers:

- "Add reactions to messages."
- "Expose `last_seen_at` on the participant payload."
- "Add an endpoint that returns the participants of a conversation."
- "Make the polling interval per-topic instead of global."

## Architecture at a glance

```
┌─────────────────────────────────────────────────────────────┐
│ Public surface (versioned, breaking changes need SemVer-major)
│   • Contracts/*                                             │
│   • Http/Resources/* (wire format)                          │
│   • config/nova-chat.php keys                               │
│   • routes/api.php paths                                    │
├─────────────────────────────────────────────────────────────┤
│ Internal (refactor freely)                                  │
│   • Concerns/* default impls                                │
│   • Support/TopicRegistry, TopicDescriptor                  │
│   • Http/Controllers/ConversationsController internals      │
│   • Vue components                                          │
└─────────────────────────────────────────────────────────────┘
```

If a change touches the top half, slow down and follow the playbook.
If it only touches the bottom half, you can be quicker.

## The five-step playbook

### Step 1 — Decide where the feature attaches

Ask: which contract owns this concept?

| If the feature is about… | Touch this contract |
|---|---|
| The thread / sidebar row | `Chattable` |
| An individual message | `ChatMessage` |
| The author of a message | `ChatParticipant` |
| Tool-wide config | none — extend `config/nova-chat.php` and `TopicDescriptor` |

If the answer is "all three" or "none of these," reconsider — that's a
signal the feature doesn't belong in the package at all.

### Step 2 — Update the contract (only if needed)

Add the method **with a default-friendly signature**:

```php
// src/Contracts/ChatMessage.php
public function reactions(): \Illuminate\Database\Eloquent\Relations\HasMany;
```

If a default makes sense without a new column, also add it to the
matching trait so the change is non-breaking:

```php
// src/Concerns/AsChatMessage.php
public function reactions(): HasMany
{
    return $this->hasMany($this->reactionModel ?? throw new \LogicException(
        'Define $reactionModel on '.static::class.' or override reactions().'
    ));
}
```

If a new column is required in the consumer's table, the change is
**SemVer-major** — document it as such in the changelog draft.

### Step 3 — Wire the controller and the JSON resource

1. Add the controller method in `src/Http/Controllers/ConversationsController.php`.
   Resolve the topic via the registry — never accept a model directly.
2. Add the JSON resource (or a new field on an existing resource) in
   `src/Http/Resources/`. Keep the field name `snake_case`.
3. Add the route to `routes/api.php`. Always under
   `/topics/{topic}/…` unless there's a strong reason otherwise.
4. Confirm the route appears:

```bash
php artisan route:list | grep nova-chat
```

### Step 4 — Surface it in the Vue UI

1. Edit the relevant component(s) in `resources/js/components/`.
2. Use `Nova.request()` for HTTP — never raw axios.
3. Run `npm run build` — `dist/js/tool.js` and `dist/js/tool.css` must
   be regenerated and **committed** (see `.ai/guidelines/frontend-workflow.md`).

### Step 5 — Update consumer-facing docs in lockstep

A package change isn't done until the consumer-facing docs are
caught up. Update **all four** of these in the same PR:

1. `README.md` if the public surface changed.
2. `resources/boost/guidelines/nova-chat.md` if there's a new convention
   consumers must follow.
3. `resources/boost/skills/nova-chat-development/SKILL.md` if the
   integration walkthrough or troubleshooting matrix changes.
4. `database/stubs/chat_messages_table.stub` if you added a recommended
   or required column.

## Verification checklist

```bash
# 1. No app-namespaced classes leaked into src/
rg -n '\\App\\\\Models\\\\' src/ routes/ config/ database/stubs/ || echo OK

# 2. Routes load
php artisan route:list | grep nova-chat

# 3. Vue rebuilt
npm run build && git status dist/

# 4. Stub still parses (if you touched it)
php -l database/stubs/chat_messages_table.stub
```

## Pitfalls

- **Adding `App\Models\X` inside `src/`** — instant contract violation.
  See `.ai/guidelines/contract-purity.md`.
- **Skipping the trait default** — a new abstract method on a contract
  breaks every existing consumer the moment they `composer update`.
- **Forgetting to rebuild `dist/`** — your code works locally, then ships
  broken to consumers because the released tag has stale JS.
- **Inlining array shaping in the controller** — the controller should
  hand off to a Resource. Inline arrays drift from the documented
  envelope and break the Vue UI silently.
- **Adding a new endpoint outside `/topics/{topic}/…`** — almost always
  wrong. The registry should resolve a topic first.

## Anti-patterns to refuse

If the user asks for any of these, push back with the reason before
implementing:

- "Add a `chat_messages` shared table to the package." → No. Each topic
  owns its own table by design (see `.ai/guidelines/database-schema.md`).
- "Reference `App\Models\Admin` in the controller so we can call its
  `notifyAdmins()` method." → No. Add `notifyAuthor()` (or similar) to
  `ChatParticipant` and let the consumer implement it.
- "Compute `is_from_admin` at query time so we can drop the column." →
  No. The denormalization is a deliberate perf optimization for the
  unread badge query.
- "Switch from polling to broadcasting in this PR." → That's a v2
  conversation, not a small feature. Confirm with the user before
  taking it on.

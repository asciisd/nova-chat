# Database schema (always-on)

The package does **not** own a single shared `chat_messages` table. Every
consuming topic provides its own table. The package only documents the
shape and ships a publishable stub at
`database/stubs/chat_messages_table.stub`.

## The required columns (and why)

| Column | Type | Required | Why it exists |
|---|---|---|---|
| `id` | bigint pk | yes | standard |
| `<host>_id` | bigint fk | yes | thread membership; cascade-delete with the host |
| `author_type` / `author_id` | morphs | yes | polymorphic author (Admin / User / …) |
| `body` | text | yes | message content |
| `is_from_admin` | bool default false | yes | denormalized — see below |
| `read_at` | timestamp nullable | yes | unread filter + read receipts |
| `created_at` / `updated_at` | timestamps | yes | sidebar ordering, relative time |
| `reference` | ulid unique | recommended | stable, public-safe id |
| `attachments` | json nullable | recommended | forward-compat for v2 attachments |
| `deleted_at` | timestamp nullable (`softDeletes()`) | required for moderation | needed for `DELETE /messages/{id}`; the controller refuses with 422 if the model doesn't use `SoftDeletes` |
| `deleted_by_type` / `deleted_by_id` | morphs nullable | recommended | audit trail — who deleted the message |
| `deletion_reason` | text nullable | recommended | audit trail — why; surfaced in admin UI |

## Package-owned tables (you maintain these)

The package owns exactly **one** table:

- `nova_chat_blocked_participants` — records globally-blocked
  participants. Migration auto-loaded from `database/migrations/`; no
  `vendor:publish` step. The columns are: `participant_type`,
  `participant_id`, polymorphic `blocked_by_*` (nullable),
  `reason` (nullable), `created_at`, `updated_at`, plus a unique index
  on `(participant_type, participant_id)`.

If you need to add a second package-owned table, mention it explicitly
in [.ai/guidelines/contract-purity.md](.ai/guidelines/contract-purity.md)
and in `resources/boost/guidelines/nova-chat.md`. The "package owns no
tables" rule no longer holds; the new rule is **the package owns
exactly the tables documented here**.

## Required indexes (non-negotiable)

```php
$table->index(['<host>_id', 'created_at']);
$table->index(['<host>_id', 'is_from_admin', 'read_at'], '<host>_messages_unread_idx');
```

The unread index is what keeps the sidebar's badge query
(`where('is_from_admin', false)->whereNull('read_at')->count()`) at
O(log n) per conversation. **Do not change this query to use
`whereHasMorph`** — that join is roughly 10× more expensive at scale,
which is the entire reason `is_from_admin` is denormalized in the first
place.

## Why `is_from_admin` is a stored bool

A naive design would compute "is admin" at query time by morphing
`author_type` against a list of admin classes. Three problems:

1. The list of "admin classes" is per-app — the package would need a
   config knob and a polymorphic join in the unread query.
2. A polymorphic join on every sidebar poll, every 4 s, on every open
   admin tab, is a load-test nightmare.
3. If a participant model swaps between "admin" and "not admin" over
   time (rare but real — e.g. demotions), historical alignment in the
   thread should reflect the role **at write time**, not now.

Hence: the column is set on the `creating` event by `AsChatMessage`,
which calls `$author->isChatAdmin()` once and stores the result. If you
override `is_from_admin` at insertion time, your value wins.

## Morph map is required, not optional

`NovaChatServiceProvider::registerMorphMap()` calls
`Relation::enforceMorphMap(config('nova-chat.morph_map'))`. The default
config ships an empty array; the consuming app populates it.

If the app skips this:

- `author_type` rows store full FQCNs (`App\Models\Admin`).
- The wire format leaks internal class names.
- Renaming `App\Models\Admin` → `App\Auth\Admin` silently breaks every
  past message.

Every guideline and skill that touches `morph_map` should remind the
consumer to register **both** participant classes and host classes —
they all morph through the same map.

## Migration stub

Anything written into `database/stubs/chat_messages_table.stub` is
published into the consuming app via `vendor:publish --tag=nova-chat-stubs`.
Keep the stub authoritative — it's the canonical reference for the
column list above. If you add a recommended column, update both the
stub and this guideline in the same commit.

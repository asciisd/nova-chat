# asciisd/nova-chat conventions

This project uses `asciisd/nova-chat`, a contract-driven Nova chat tool. When the user asks for chat / messaging / threaded comments / "WhatsApp-style" UI inside Nova, **reuse this package** instead of rolling custom code.

## Core idea — contracts, not concrete models

The package never references domain classes. Every consuming model plugs in through one of three interfaces:

- `Asciisd\NovaChat\Contracts\Chattable` — the host model (Signal, Ticket, Order, …) that owns a thread
- `Asciisd\NovaChat\Contracts\ChatMessage` — the message model
- `Asciisd\NovaChat\Contracts\ChatParticipant` — the author model (Admin, User, Customer, …)

Drop-in traits cover the boilerplate: `HasChat`, `AsChatMessage`, `AsChatParticipant`.

## Non-negotiables when adding chat to a model

- **Each topic owns its own message table.** Do not share one `chat_messages` table across topics — every host gets its own `*_messages` table with the required columns.
- **Required message columns:** FK to host, `author_type`/`author_id` (morphs), `body` (text), `is_from_admin` (bool default false), `read_at` (timestamp nullable), `created_at`/`updated_at`. `reference` (ulid) and `attachments` (json) are recommended but optional.
- **Required indexes:** composite on `(fk, created_at)` and `(fk, is_from_admin, read_at)`. The unread index is non-optional — the sidebar's unread badge query depends on it.
- **Admin author override:** the participant class that authors messages from the Nova side must override `isChatAdmin(): bool { return true; }`. Other participant classes inherit the default `false`.
- **Morph map entries are required**, not optional. Add every Chattable host model and every ChatParticipant author class to `config('nova-chat.morph_map')`. Skipping this stores full class names in `author_type`, which breaks refactors and pollutes JSON payloads.
- **Set `is_from_admin` at write time**, not derived. The controller does this automatically when admins send via the API; if you create messages elsewhere (jobs, factories, seeders), set the column explicitly — never compute it later.
- **Topics declare BOTH a host model and a message model** in `config('nova-chat.topics')`. Pointing only at the host is invalid and will throw at boot.

## Where things live

- Contracts → `Asciisd\NovaChat\Contracts\*`
- Traits → `Asciisd\NovaChat\Concerns\*`
- Config → `config/nova-chat.php` (published from package)
- Reference migration → `database/stubs/chat_messages_table.stub` inside the package; publish with `--tag=nova-chat-stubs`
- API routes → mounted automatically at `/nova-vendor/nova-chat/*` under Nova auth middleware
- Tool sidebar route → `/nova/nova-chat`

## Asset rebuild

When the user edits Vue files inside the package, rebuild from the package source — not from the consuming app:

```bash
cd vendor/asciisd/nova-chat   # or the path-repo source
npm install                   # first time only
npm run build
```

The Tool class registers `dist/js/tool.js` + `dist/js/tool.css` with Nova; the consuming app's Vite pipeline is irrelevant.

## What NOT to do

- Don't create a parallel chat implementation when the project already has this package — extend it instead.
- Don't add custom `chat_messages`-named tables; the package does not own such a table by design.
- Don't reference `Signal`, `Admin`, `User`, or any other app-namespaced class from inside `vendor/asciisd/nova-chat/src/` — that's a contract-integrity violation. The package is generic on purpose.
- Don't compute `is_from_admin` via `whereHasMorph(...)` in unread queries. The denormalized column exists precisely so unread badges stay cheap.

For the full integration walkthrough (new topic from scratch, code samples, migration template, troubleshooting), invoke the **`nova-chat-development`** skill.

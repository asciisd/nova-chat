# Contract purity (always-on)

This package's value proposition is "drop-in chat for any Nova-managed
model." That only works because the source code is **generic on purpose**.
Three rules keep it that way.

## 1. `src/` may not name an app-namespaced class

Anywhere inside `src/` (and `routes/`, `config/`, `database/stubs/`),
the only classes referenced are:

- Framework / Nova (`Illuminate\…`, `Laravel\Nova\…`, `Inertia\…`).
- This package (`Asciisd\NovaChat\…`).
- The three contracts:
  - `Asciisd\NovaChat\Contracts\Chattable`
  - `Asciisd\NovaChat\Contracts\ChatMessage`
  - `Asciisd\NovaChat\Contracts\ChatParticipant`

If you find yourself reaching for `App\Models\Signal`, `App\Models\Admin`,
or any other concrete domain class — **stop**. Add a method to the
relevant contract instead, and let the consuming app implement it.

Quick check before pushing:

```bash
rg -n '\\App\\\\Models\\\\' src/ routes/ config/ database/stubs/
# expected: zero matches
```

## 2. Type-hint contracts, not models

Inside the package, controllers, resources, and traits should accept
**interfaces**:

```php
// Good
public function build(Chattable $host, ChatMessage $message): array { … }

// Bad — couples the package to a specific app model
public function build(\App\Models\Signal $signal, \App\Models\SignalMessage $msg): array { … }
```

The TopicRegistry already returns descriptors that hand you the right
class names dynamically — use `$descriptor->messageModel()` etc., never
`Signal::class`.

## 3. New behavior goes on the contract, then the trait, then nowhere else

Adding a new capability ("show last seen", "support reactions") follows
this order:

1. **Contract** — add the method signature to one of `Chattable`,
   `ChatMessage`, or `ChatParticipant`. This is a public API change and
   may be a breaking change for consumers.
2. **Trait** — provide a sensible default in `HasChat`, `AsChatMessage`,
   or `AsChatParticipant` so the new method is non-breaking for apps
   already using the trait.
3. **Controller / resource** — call the contract method.
4. **Vue UI** — render whatever the API now exposes.

Never wire a feature directly from controller → app-side concrete class.
That's how the package gets entangled with one consumer and stops being
reusable.

## What "Chattable" / "ChatMessage" / "ChatParticipant" guarantee

| Contract | Required surface | Why |
|---|---|---|
| `Chattable` | `chatMessages(): HasMany`, `chatTitle(): string`, `chatSubtitle(): ?string`, `chatBadge(): ?string` | Sidebar row rendering + thread loading |
| `ChatMessage` | `chattable(): BelongsTo`, `author(): MorphTo`, `getBody(): string`, `isRead(): bool`, `markAsRead(): void`, `isFromAdmin(): bool` | Wire format, read receipts, alignment |
| `ChatParticipant` | `chatDisplayName(): string`, `chatAvatarUrl(): ?string`, `isChatAdmin(): bool` | Author block in the UI, alignment, unread filter |

Adding a new method here is a SemVer-major change unless you also add a
default implementation in the matching trait that **does not depend on
new database columns**. Otherwise you've broken every existing consumer
on `composer update`.

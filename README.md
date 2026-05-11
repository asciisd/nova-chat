# asciisd/nova-chat

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asciisd/nova-chat.svg?style=flat-square)](https://packagist.org/packages/asciisd/nova-chat)
[![Total Downloads](https://img.shields.io/packagist/dt/asciisd/nova-chat.svg?style=flat-square)](https://packagist.org/packages/asciisd/nova-chat)
[![PHP Version](https://img.shields.io/packagist/php-v/asciisd/nova-chat.svg?style=flat-square)](https://packagist.org/packages/asciisd/nova-chat)
[![License](https://img.shields.io/packagist/l/asciisd/nova-chat.svg?style=flat-square)](LICENSE)

A reusable, contract-driven WhatsApp-style chat tool for Laravel Nova.

The package never assumes a single shared `chat_messages` table. Each project plugs in its own host model (a "topic"), its own message model, and its own author models. Everything connects through three small interfaces.

## Requirements

- PHP ^8.3
- Laravel Nova ^5.0

## Installation

```bash
composer require asciisd/nova-chat
php artisan vendor:publish --tag=nova-chat-config
```

Register the tool in `app/Providers/NovaServiceProvider.php`:

```php
public function tools(): array
{
    return [
        new \Asciisd\NovaChat\NovaChat,
    ];
}
```

Build the Vue bundle inside the package:

```bash
cd vendor/asciisd/nova-chat
npm install
npm run build
```

(If you're consuming via a path repo / monorepo, build inside the package source directory once and rebuild when you change Vue files.)

## How it works

The package provides three interfaces. Implement them on **your** models — the package never references domain classes directly.

### 1. The host model (the "thread")

```php
use Asciisd\NovaChat\Contracts\Chattable;
use Asciisd\NovaChat\Concerns\HasChat;

class Signal extends Model implements Chattable
{
    use HasChat;

    public function chatMessages(): HasMany
    {
        return $this->hasMany(SignalMessage::class);
    }

    public function chatTitle(): string     { return $this->title; }
    public function chatSubtitle(): ?string { return strtoupper($this->order_type); }
    public function chatBadge(): ?string    { return $this->status?->value; }
}
```

### 2. The message model

```php
use Asciisd\NovaChat\Contracts\ChatMessage;
use Asciisd\NovaChat\Concerns\AsChatMessage;

class SignalMessage extends Model implements ChatMessage
{
    use AsChatMessage;

    protected $fillable = ['signal_id', 'body', 'attachments', 'read_at', 'is_from_admin', 'author_type', 'author_id'];
    protected $casts    = ['attachments' => 'array', 'read_at' => 'datetime', 'is_from_admin' => 'bool'];

    public function chattable(): BelongsTo
    {
        return $this->belongsTo(Signal::class, 'signal_id');
    }
}
```

#### Required columns on your message table

| Column                       | Type                  | Notes                                                |
|------------------------------|-----------------------|------------------------------------------------------|
| `id`                         | bigint pk             | standard                                             |
| FK to host                   | bigint                | name is flexible (`signal_id`, `ticket_id`, …)       |
| `author_type` / `author_id`  | polymorphic morph     | `$table->morphs('author')`                           |
| `body`                       | text                  | message content                                      |
| `is_from_admin`              | bool, default `false` | set at write time; cheap unread queries              |
| `read_at`                    | timestamp nullable    | read receipts                                        |
| `created_at` / `updated_at`  | timestamps            | sidebar ordering + relative time                     |

Recommended optional: `reference` (ulid), `attachments` (json).

Recommended indexes: `(fk, created_at)` and `(fk, is_from_admin, read_at)`.

A reference migration is shipped in `database/stubs/chat_messages_table.stub`.

### 3. Author models (Admin / User / Customer / …)

```php
use Asciisd\NovaChat\Contracts\ChatParticipant;
use Asciisd\NovaChat\Concerns\AsChatParticipant;

class Admin extends Authenticatable implements ChatParticipant
{
    use AsChatParticipant;

    public function isChatAdmin(): bool { return true; }
}

class User extends Authenticatable implements ChatParticipant
{
    use AsChatParticipant; // isChatAdmin() defaults to false
}
```

### 4. Register the topic in `config/nova-chat.php`

```php
'admin_guard' => 'admin',

'morph_map' => [
    'admin'  => \App\Models\Admin::class,
    'user'   => \App\Models\User::class,
    'signal' => \App\Models\Signal::class,
],

'topics' => [
    'signal' => [
        'model'         => \App\Models\Signal::class,
        'message_model' => \App\Models\SignalMessage::class,
        'label'         => 'Signals',
        'icon'          => 'currency-dollar',
        'default'       => true,
    ],
],

'poll_interval_ms' => [
    'sidebar' => 4000,
    'thread'  => 3000,
],
```

Add more topics any time — the sidebar grows a tab switcher automatically.

## API surface (admin auth required)

All routes live under `/nova-vendor/nova-chat/` and are protected by Nova's API middleware (which resolves the configured `admin_guard`):

| Method | Path                                            |
|--------|-------------------------------------------------|
| GET    | `/topics`                                       |
| GET    | `/topics/{topic}/conversations`                 |
| GET    | `/topics/{topic}/conversations/{id}/messages`   |
| POST   | `/topics/{topic}/conversations/{id}/messages`   |
| POST   | `/topics/{topic}/conversations/{id}/read`       |

## Laravel Boost integration

This package ships AI guidelines and skills for [Laravel Boost](https://laravel.com/docs/13.x/boost):

- `resources/boost/guidelines/nova-chat.md` — high-level conventions, always loaded when Boost detects the package.
- `resources/boost/skills/nova-chat-development/SKILL.md` — on-demand integration playbook (six-step walkthrough for adding a new Chattable host, troubleshooting matrix, API contract).

Run `php artisan boost:install` (or `boost:update --discover` after `composer require`) to publish them into the consuming app.

## v1 caveats

- **Polling only.** No Reverb/Pusher. Default cadence: sidebar 4 s, thread 3 s. Polling pauses while the tab is hidden.
- **Text only.** The `attachments` JSON column is preserved in the schema but not exposed in the v1 UI.

## License

MIT — see [LICENSE](LICENSE).

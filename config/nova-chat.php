<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin guard
    |--------------------------------------------------------------------------
    |
    | The auth guard used to identify the admin who is authoring messages
    | inside the Nova tool. The resolved user must implement the
    | Asciisd\NovaChat\Contracts\ChatParticipant interface and return true
    | from isChatAdmin().
    */
    'admin_guard' => env('NOVA_CHAT_ADMIN_GUARD', config('nova.guard') ?: 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Morph map
    |--------------------------------------------------------------------------
    |
    | These aliases are merged into Eloquent's morph map, keeping the values
    | stored in chattable_type / author_type columns short and refactor-safe.
    */
    'morph_map' => [
        // 'admin'  => \App\Models\Admin::class,
        // 'user'   => \App\Models\User::class,
        // 'signal' => \App\Models\Signal::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Topics
    |--------------------------------------------------------------------------
    |
    | Each entry registers one chat topic. The package never assumes which
    | tables back the chat — each topic plugs in its own host model
    | (implements Chattable) and message model (implements ChatMessage).
    |
    | The message table the developer points at MUST have these columns:
    |   - foreign key to the host model (any column name)
    |   - author_type / author_id (polymorphic morph)
    |   - body            (text)
    |   - is_from_admin   (bool, default false)
    |   - read_at         (timestamp, nullable)
    |   - created_at / updated_at
    |
    | Recommended optional: reference (ulid), attachments (json).
    */
    'topics' => [
        // 'signal' => [
        //     'model'         => \App\Models\Signal::class,
        //     'message_model' => \App\Models\SignalMessage::class,
        //     'label'         => 'Signals',
        //     'icon'          => 'currency-dollar',
        //     'default'       => true,
        //     'query'         => null, // optional fn (Builder $q): Builder
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling intervals (milliseconds)
    |--------------------------------------------------------------------------
    */
    'poll_interval_ms' => [
        'sidebar' => 4000,
        'thread'  => 3000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | `allow_block`  — admins can block participants from chatting globally.
    |                  Backed by the package-owned table
    |                  `nova_chat_blocked_participants`. The block is enforced
    |                  by the consumer's user-side write endpoint via
    |                  $user->isChatBlocked() (the package's admin POST is
    |                  unaffected).
    |
    | `allow_delete` — admins can soft-delete user messages. Requires the
    |                  consumer's message model to `use SoftDeletes` and the
    |                  table to have a `deleted_at` column. The recommended
    |                  `deleted_by_*` and `deletion_reason` columns capture
    |                  who deleted what and why.
    |
    | `max_reason_length` applies to both block reasons and delete reasons.
    */
    'moderation' => [
        'allow_block'       => true,
        'allow_delete'      => true,
        'max_reason_length' => 500,
    ],

];

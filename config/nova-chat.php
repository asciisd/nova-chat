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

];

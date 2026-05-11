<template>
    <div class="nova-chat-bubble-row" :class="{ 'is-admin': message.is_from_admin }">
        <div class="nova-chat-bubble">
            <div class="nova-chat-bubble-meta">
                <span class="nova-chat-bubble-author">{{ message.author?.name || 'Unknown' }}</span>
                <span class="nova-chat-bubble-time" :title="message.created_at">
                    {{ formatRelative(message.created_at) }}
                </span>
            </div>
            <div class="nova-chat-bubble-body">{{ message.body }}</div>
        </div>
    </div>
</template>

<script setup>
import { formatRelative } from '../lib/time.js'

defineProps({
    message: { type: Object, required: true },
})
</script>

<style>
.nova-chat-bubble-row {
    display: flex;
    margin-bottom: 0.75rem;
    justify-content: flex-start;
}

.nova-chat-bubble-row.is-admin {
    justify-content: flex-end;
}

.nova-chat-bubble {
    max-width: 70%;
    padding: 0.625rem 0.875rem;
    border-radius: 0.875rem;
    background: var(--nc-bg-bubble-other);
    color: var(--nc-text-primary);
    border-top-left-radius: 0.25rem;
}

.nova-chat-bubble-row.is-admin .nova-chat-bubble {
    background: var(--nc-accent);
    color: var(--nc-text-on-accent);
    border-top-left-radius: 0.875rem;
    border-top-right-radius: 0.25rem;
}

.nova-chat-bubble-meta {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.6875rem;
    margin-bottom: 0.25rem;
    opacity: 0.8;
}

.nova-chat-bubble-author {
    font-weight: 600;
}

.nova-chat-bubble-time {
    font-weight: 400;
}

.nova-chat-bubble-body {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: 0.9rem;
    line-height: 1.4;
}
</style>

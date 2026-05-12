<template>
    <div
        class="nova-chat-bubble-row"
        :class="{ 'is-admin': message.is_from_admin, 'is-deleted': isDeleted }"
    >
        <div class="nova-chat-bubble">
            <div class="nova-chat-bubble-meta">
                <span class="nova-chat-bubble-author">
                    {{ message.author?.name || 'Unknown' }}
                    <span v-if="message.author?.is_blocked" class="nova-chat-bubble-pill">Blocked</span>
                </span>
                <span class="nova-chat-bubble-time" :title="message.created_at">
                    {{ formatRelative(message.created_at) }}
                </span>
            </div>

            <div class="nova-chat-bubble-body" :class="{ 'is-deleted-body': isDeleted }">
                <template v-if="isDeleted">
                    <em>{{ message.body }}</em>
                </template>
                <template v-else>{{ message.body }}</template>
            </div>

            <div v-if="isDeleted" class="nova-chat-bubble-deleted-meta">
                Deleted{{ message.deleted_by?.name ? ` by ${message.deleted_by.name}` : '' }}
                <span v-if="message.deletion_reason">— {{ message.deletion_reason }}</span>
            </div>

            <div v-if="hasMenu" class="nova-chat-bubble-menu" @click.stop>
                <button
                    type="button"
                    class="nova-chat-bubble-menu-trigger"
                    :aria-label="'Message actions'"
                    @click="toggleMenu"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" aria-hidden="true">
                        <circle cx="4" cy="10" r="1.6" />
                        <circle cx="10" cy="10" r="1.6" />
                        <circle cx="16" cy="10" r="1.6" />
                    </svg>
                </button>
                <div v-if="open" class="nova-chat-bubble-menu-popover" role="menu">
                    <button
                        v-if="canDelete"
                        type="button"
                        class="nova-chat-bubble-menu-item is-danger"
                        @click="emitAction('delete')"
                    >
                        Delete message
                    </button>
                    <button
                        v-if="canBlockAuthor"
                        type="button"
                        class="nova-chat-bubble-menu-item"
                        @click="emitAction(message.author?.is_blocked ? 'unblock' : 'block')"
                    >
                        {{ message.author?.is_blocked ? 'Unblock author' : 'Block author' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { formatRelative } from '../lib/time.js'

const props = defineProps({
    message: { type: Object, required: true },
    canDelete: { type: Boolean, default: false },
    canBlock: { type: Boolean, default: false },
})

const emit = defineEmits(['action'])

const open = ref(false)

const isDeleted = computed(() => Boolean(props.message?.deleted_at))

// Don't expose "block author" for an admin's own messages.
const canBlockAuthor = computed(
    () => props.canBlock && !props.message?.is_from_admin && props.message?.author?.id != null,
)

const hasMenu = computed(() => {
    if (isDeleted.value) return false
    return Boolean(props.canDelete) || canBlockAuthor.value
})

function toggleMenu() {
    open.value = !open.value
}

function emitAction(action) {
    open.value = false
    emit('action', { action, message: props.message })
}

function onDocumentClick() {
    open.value = false
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))
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
    position: relative;
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

.nova-chat-bubble-row.is-deleted .nova-chat-bubble {
    opacity: 0.6;
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
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.nova-chat-bubble-pill {
    font-size: 0.625rem;
    font-weight: 600;
    padding: 0.0625rem 0.375rem;
    border-radius: 9999px;
    background: var(--nc-danger);
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.05em;
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

.nova-chat-bubble-body.is-deleted-body {
    font-style: italic;
}

.nova-chat-bubble-deleted-meta {
    margin-top: 0.25rem;
    font-size: 0.6875rem;
    opacity: 0.75;
    font-style: italic;
}

.nova-chat-bubble-menu {
    position: absolute;
    top: 0.25rem;
    right: 0.25rem;
}

.nova-chat-bubble-row:not(.is-admin) .nova-chat-bubble-menu {
    right: auto;
    left: auto;
    top: 0.25rem;
    right: 0.25rem;
}

.nova-chat-bubble-menu-trigger {
    border: none;
    background: transparent;
    color: inherit;
    opacity: 0;
    cursor: pointer;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    transition: opacity 120ms ease, background-color 120ms ease;
}

.nova-chat-bubble:hover .nova-chat-bubble-menu-trigger,
.nova-chat-bubble-menu-trigger:focus {
    opacity: 0.85;
}

.nova-chat-bubble-menu-trigger:hover {
    background: rgba(0, 0, 0, 0.08);
    opacity: 1;
}

.nova-chat-bubble-row.is-admin .nova-chat-bubble-menu-trigger:hover {
    background: rgba(255, 255, 255, 0.18);
}

.nova-chat-bubble-menu-popover {
    position: absolute;
    top: 1.5rem;
    right: 0;
    min-width: 10rem;
    background: var(--nc-bg-surface);
    color: var(--nc-text-primary);
    border: 1px solid var(--nc-border);
    border-radius: 0.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    z-index: 20;
}

.nova-chat-bubble-menu-item {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    color: inherit;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    cursor: pointer;
}

.nova-chat-bubble-menu-item:hover {
    background: var(--nc-bg-hover);
}

.nova-chat-bubble-menu-item.is-danger {
    color: var(--nc-danger);
}
</style>

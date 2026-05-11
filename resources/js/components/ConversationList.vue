<template>
    <aside class="nova-chat-list">
        <div class="nova-chat-list-search">
            <input
                v-model="search"
                type="search"
                placeholder="Search conversations…"
                @input="onSearchInput"
            />
        </div>

        <div v-if="loading && conversations.length === 0" class="nova-chat-list-empty">
            Loading…
        </div>
        <div v-else-if="conversations.length === 0" class="nova-chat-list-empty">
            No conversations yet.
        </div>
        <ul v-else class="nova-chat-list-items">
            <li
                v-for="row in conversations"
                :key="row.id"
                class="nova-chat-list-row"
                :class="{ 'is-active': row.id === activeId, 'is-unread': row.unread_count > 0 }"
                @click="$emit('select', row.id)"
            >
                <div class="nova-chat-list-row-top">
                    <span class="nova-chat-list-row-title">{{ row.title }}</span>
                    <span v-if="row.latest_message?.created_at" class="nova-chat-list-row-time">
                        {{ formatRelative(row.latest_message.created_at) }}
                    </span>
                </div>
                <div class="nova-chat-list-row-mid">
                    <span v-if="row.subtitle" class="nova-chat-list-row-subtitle">{{ row.subtitle }}</span>
                    <span v-if="row.badge" class="nova-chat-list-row-badge">{{ row.badge }}</span>
                </div>
                <div class="nova-chat-list-row-bot">
                    <span class="nova-chat-list-row-preview">
                        <span v-if="row.latest_message?.is_from_admin" class="nova-chat-list-row-preview-prefix">You:</span>
                        {{ row.latest_message?.body_excerpt || 'No messages yet.' }}
                    </span>
                    <span v-if="row.unread_count > 0" class="nova-chat-list-row-unread">
                        {{ row.unread_count }}
                    </span>
                </div>
            </li>
        </ul>
    </aside>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { formatRelative } from '../lib/time.js'

const props = defineProps({
    topicKey: { type: String, required: true },
    activeId: { type: [Number, String, null], default: null },
    pollInterval: { type: Number, default: 4000 },
})

defineEmits(['select'])

const conversations = ref([])
const loading = ref(false)
const search = ref('')
let pollHandle = null
let searchDebounce = null

async function fetchList() {
    loading.value = true
    try {
        const params = {}
        if (search.value.trim()) params.search = search.value.trim()
        const { data } = await Nova.request().get(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations`,
            { params },
        )
        conversations.value = data.data || []
    } finally {
        loading.value = false
    }
}

function onSearchInput() {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(fetchList, 300)
}

function startPolling() {
    stopPolling()
    pollHandle = setInterval(() => {
        if (document.visibilityState !== 'hidden') fetchList()
    }, props.pollInterval)
}

function stopPolling() {
    if (pollHandle) clearInterval(pollHandle)
    pollHandle = null
}

watch(() => props.topicKey, () => {
    search.value = ''
    fetchList()
})

onMounted(() => {
    fetchList()
    startPolling()
})

onBeforeUnmount(() => {
    stopPolling()
    if (searchDebounce) clearTimeout(searchDebounce)
})
</script>

<style>
.nova-chat-list {
    border-right: 1px solid var(--nc-border);
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: var(--nc-bg-surface);
}

.nova-chat-list-search {
    padding: 0.75rem;
    border-bottom: 1px solid var(--nc-border);
}

.nova-chat-list-search input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid var(--nc-border);
    background: var(--nc-bg-input);
    color: var(--nc-text-primary);
    font-size: 0.875rem;
}

.nova-chat-list-search input::placeholder {
    color: var(--nc-text-faint);
}

.nova-chat-list-search input:focus {
    outline: none;
    border-color: var(--nc-accent);
}

.nova-chat-list-items {
    list-style: none;
    margin: 0;
    padding: 0;
    overflow-y: auto;
    flex: 1;
}

.nova-chat-list-empty {
    padding: 1.5rem;
    text-align: center;
    color: var(--nc-text-muted);
    font-size: 0.875rem;
}

.nova-chat-list-row {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--nc-border-soft);
    cursor: pointer;
}

.nova-chat-list-row:hover {
    background: var(--nc-bg-hover);
}

.nova-chat-list-row.is-active {
    background: var(--nc-bg-active);
}

.nova-chat-list-row-top,
.nova-chat-list-row-mid,
.nova-chat-list-row-bot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.nova-chat-list-row-title {
    font-weight: 600;
    color: var(--nc-text-primary);
    font-size: 0.9375rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nova-chat-list-row-time {
    color: var(--nc-text-muted);
    font-size: 0.75rem;
    white-space: nowrap;
    flex-shrink: 0;
}

.nova-chat-list-row-subtitle {
    color: var(--nc-text-secondary);
    font-size: 0.8125rem;
}

.nova-chat-list-row-badge {
    font-size: 0.6875rem;
    padding: 0.1rem 0.5rem;
    border-radius: 9999px;
    background: var(--nc-bg-badge);
    color: var(--nc-text-on-badge);
}

.nova-chat-list-row-preview {
    color: var(--nc-text-secondary);
    font-size: 0.8125rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.nova-chat-list-row-preview-prefix {
    color: var(--nc-text-faint);
    margin-right: 0.25rem;
}

.nova-chat-list-row.is-unread .nova-chat-list-row-preview {
    color: var(--nc-text-primary);
    font-weight: 500;
}

.nova-chat-list-row-unread {
    background: var(--nc-accent);
    color: var(--nc-text-on-accent);
    border-radius: 9999px;
    padding: 0 0.45rem;
    font-size: 0.6875rem;
    min-width: 1.1rem;
    text-align: center;
}
</style>

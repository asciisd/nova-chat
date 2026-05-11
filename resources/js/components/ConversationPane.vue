<template>
    <section class="nova-chat-pane">
        <header v-if="header" class="nova-chat-pane-header">
            <div>
                <h2 class="nova-chat-pane-title">{{ header.title }}</h2>
                <p v-if="header.subtitle" class="nova-chat-pane-subtitle">{{ header.subtitle }}</p>
            </div>
            <span v-if="header.badge" class="nova-chat-pane-badge">{{ header.badge }}</span>
        </header>

        <div ref="scrollEl" class="nova-chat-pane-scroll">
            <div v-if="loading && messages.length === 0" class="nova-chat-pane-empty">Loading…</div>
            <div v-else-if="messages.length === 0" class="nova-chat-pane-empty">
                No messages yet. Send the first one.
            </div>
            <MessageBubble
                v-for="m in messages"
                :key="m.id"
                :message="m"
            />
        </div>

        <MessageComposer
            :topic-key="topicKey"
            :conversation-id="conversationId"
            @sent="onSent"
        />
    </section>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import MessageBubble from './MessageBubble.vue'
import MessageComposer from './MessageComposer.vue'

const props = defineProps({
    topicKey: { type: String, required: true },
    conversationId: { type: [Number, String], required: true },
    pollInterval: { type: Number, default: 3000 },
})

const messages = ref([])
const loading = ref(false)
const header = ref(null)
const scrollEl = ref(null)
let lastSeenId = 0
let pollHandle = null

async function loadInitial() {
    loading.value = true
    try {
        const { data } = await Nova.request().get(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations/${props.conversationId}/messages`,
        )
        messages.value = data.data || []
        lastSeenId = messages.value.length
            ? messages.value[messages.value.length - 1].id
            : 0
        await nextTick()
        scrollToBottom()
        await markRead()
        await loadHeader()
    } finally {
        loading.value = false
    }
}

async function pollDelta() {
    if (document.visibilityState === 'hidden') return
    try {
        const { data } = await Nova.request().get(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations/${props.conversationId}/messages`,
            { params: { after: lastSeenId } },
        )
        const fresh = data.data || []
        if (fresh.length) {
            // Filter dupes already present (covers the optimistic append case).
            const known = new Set(messages.value.map((m) => m.id))
            const adds = fresh.filter((m) => !known.has(m.id))
            if (adds.length) {
                messages.value = [...messages.value, ...adds]
                lastSeenId = messages.value[messages.value.length - 1].id
                await nextTick()
                scrollToBottom()
                if (adds.some((m) => !m.is_from_admin)) await markRead()
            }
        }
    } catch (_) {
        // swallow — next tick will retry
    }
}

async function markRead() {
    try {
        await Nova.request().post(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations/${props.conversationId}/read`,
        )
    } catch (_) {
        // best-effort
    }
}

async function loadHeader() {
    try {
        const { data } = await Nova.request().get(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations`,
            { params: { search: '', per_page: 50 } },
        )
        const row = (data.data || []).find((c) => c.id === Number(props.conversationId))
        if (row) {
            header.value = { title: row.title, subtitle: row.subtitle, badge: row.badge }
        }
    } catch (_) {}
}

function onSent(message) {
    messages.value.push(message)
    lastSeenId = message.id
    nextTick(scrollToBottom)
}

function scrollToBottom() {
    const el = scrollEl.value
    if (el) el.scrollTop = el.scrollHeight
}

onMounted(() => {
    loadInitial()
    pollHandle = setInterval(pollDelta, props.pollInterval)
})

onBeforeUnmount(() => {
    if (pollHandle) clearInterval(pollHandle)
    pollHandle = null
})
</script>

<style>
.nova-chat-pane {
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: #fff;
}

.nova-chat-pane-header {
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--color-gray-200, #e5e7eb);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    background: var(--color-gray-50, #f9fafb);
}

.nova-chat-pane-title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-gray-900, #111827);
}

.nova-chat-pane-subtitle {
    margin: 0.125rem 0 0;
    font-size: 0.75rem;
    color: var(--color-gray-500, #6b7280);
}

.nova-chat-pane-badge {
    font-size: 0.6875rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    background: var(--color-gray-200, #e5e7eb);
    color: var(--color-gray-800, #1f2937);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nova-chat-pane-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 1rem 1.25rem;
    background: var(--color-gray-50, #f9fafb);
}

.nova-chat-pane-empty {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-gray-500, #6b7280);
    font-size: 0.875rem;
}
</style>

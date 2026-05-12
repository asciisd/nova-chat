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
                :can-delete="moderation.allow_delete"
                :can-block="moderation.allow_block"
                @action="onMessageAction"
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
    moderation: {
        type: Object,
        default: () => ({ allow_block: true, allow_delete: true }),
    },
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

async function onMessageAction({ action, message }) {
    if (action === 'delete') return await deleteMessage(message)
    if (action === 'block') return await blockAuthor(message)
    if (action === 'unblock') return await unblockAuthor(message)
}

async function deleteMessage(message) {
    const reason = window.prompt(
        'Delete this message? Optional reason for the audit trail:',
        '',
    )
    if (reason === null) return // cancel
    try {
        await Nova.request().delete(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations/${props.conversationId}/messages/${message.id}`,
            { data: reason ? { reason } : {} },
        )
        // Optimistically mark as deleted; the next poll will replace with the
        // server's record (including deleted_by/name).
        const idx = messages.value.findIndex((m) => m.id === message.id)
        if (idx !== -1) {
            messages.value[idx] = {
                ...messages.value[idx],
                deleted_at: new Date().toISOString(),
                deletion_reason: reason || null,
            }
        }
    } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to delete message.'
        Nova.error?.(msg) ?? window.alert(msg)
    }
}

async function blockAuthor(message) {
    const author = message.author
    if (!author?.type || author.id == null) return
    const reason = window.prompt(
        `Block ${author.name || 'this user'} from chatting? Optional reason:`,
        '',
    )
    if (reason === null) return
    try {
        await Nova.request().post('/nova-vendor/nova-chat/blocks', {
            participant_type: author.type,
            participant_id: author.id,
            reason: reason || null,
        })
        applyAuthorBlockFlag(author, true)
    } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to block author.'
        Nova.error?.(msg) ?? window.alert(msg)
    }
}

async function unblockAuthor(message) {
    const author = message.author
    if (!author?.type || author.id == null) return
    try {
        await Nova.request().delete(
            `/nova-vendor/nova-chat/blocks/${encodeURIComponent(author.type)}/${encodeURIComponent(author.id)}`,
        )
        applyAuthorBlockFlag(author, false)
    } catch (e) {
        const msg = e?.response?.data?.message || 'Failed to unblock author.'
        Nova.error?.(msg) ?? window.alert(msg)
    }
}

function applyAuthorBlockFlag(author, value) {
    messages.value = messages.value.map((m) =>
        m.author && m.author.type === author.type && String(m.author.id) === String(author.id)
            ? { ...m, author: { ...m.author, is_blocked: value } }
            : m,
    )
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
    background: var(--nc-bg-surface);
}

.nova-chat-pane-header {
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--nc-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    background: var(--nc-bg-app);
}

.nova-chat-pane-title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--nc-text-primary);
}

.nova-chat-pane-subtitle {
    margin: 0.125rem 0 0;
    font-size: 0.75rem;
    color: var(--nc-text-muted);
}

.nova-chat-pane-badge {
    font-size: 0.6875rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    background: var(--nc-bg-badge);
    color: var(--nc-text-on-badge);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nova-chat-pane-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 1rem 1.25rem;
    background: var(--nc-bg-app);
}

.nova-chat-pane-empty {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--nc-text-muted);
    font-size: 0.875rem;
}
</style>

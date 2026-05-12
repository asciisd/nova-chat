<template>
    <div class="nova-chat-root">
        <header class="nova-chat-header">
            <h1 class="nova-chat-title">Chat</h1>
            <TopicTabs
                v-if="topics.length > 1"
                :topics="topics"
                :active="activeTopic?.key"
                @change="onTopicChange"
            />
        </header>

        <div v-if="loading" class="nova-chat-loading">Loading…</div>
        <div v-else-if="!activeTopic" class="nova-chat-empty">
            <p>No chat topics are configured.</p>
            <p class="nova-chat-empty-hint">
                Register at least one topic in <code>config/nova-chat.php</code>.
            </p>
        </div>
        <div v-else class="nova-chat-body">
            <ConversationList
                :topic-key="activeTopic.key"
                :poll-interval="pollIntervals.sidebar"
                :active-id="activeConversationId"
                @select="onConversationSelect"
            />
            <ConversationPane
                v-if="activeConversationId"
                :key="`${activeTopic.key}:${activeConversationId}`"
                :topic-key="activeTopic.key"
                :conversation-id="activeConversationId"
                :poll-interval="pollIntervals.thread"
                :moderation="moderation"
            />
            <div v-else class="nova-chat-placeholder">
                Select a conversation to start chatting.
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import TopicTabs from '../components/TopicTabs.vue'
import ConversationList from '../components/ConversationList.vue'
import ConversationPane from '../components/ConversationPane.vue'

const loading = ref(true)
const topics = ref([])
const activeTopic = ref(null)
const activeConversationId = ref(null)
const pollIntervals = ref({ sidebar: 4000, thread: 3000 })
const moderation = ref({ allow_block: true, allow_delete: true })

async function loadTopics() {
    try {
        const { data } = await Nova.request().get('/nova-vendor/nova-chat/topics')
        topics.value = data.data || []
        pollIntervals.value = data.config || pollIntervals.value
        if (data.moderation) moderation.value = data.moderation
        activeTopic.value = topics.value.find((t) => t.default) || topics.value[0] || null
    } finally {
        loading.value = false
    }
}

function onTopicChange(topic) {
    activeTopic.value = topic
    activeConversationId.value = null
}

function onConversationSelect(id) {
    activeConversationId.value = id
}

onMounted(() => {
    document.title = 'Chat – Nova'
    loadTopics()
})
</script>

<style>
/*
 * Theme tokens — single source of truth. light-dark() resolves based on the
 * cascaded color-scheme, so values flip when Nova toggles dark mode (.dark
 * class) or when the user's OS prefers dark and Nova is on auto.
 */
.nova-chat-root {
    color-scheme: light dark;

    /* surfaces */
    --nc-bg-app:          light-dark(#f9fafb, #0f172a);
    --nc-bg-surface:      light-dark(#ffffff, #1e293b);
    --nc-bg-input:        light-dark(#f9fafb, #0f172a);
    --nc-bg-hover:        light-dark(#f9fafb, #233044);
    --nc-bg-active:       light-dark(#eff6ff, rgba(59, 130, 246, 0.18));
    --nc-bg-bubble-other: light-dark(#f3f4f6, #334155);
    --nc-bg-badge:        light-dark(#e5e7eb, #334155);
    --nc-bg-tab:          light-dark(#f3f4f6, #334155);

    /* borders */
    --nc-border:          light-dark(#e5e7eb, #334155);
    --nc-border-soft:     light-dark(#f3f4f6, #1e293b);

    /* text */
    --nc-text-primary:    light-dark(#111827, #f1f5f9);
    --nc-text-secondary:  light-dark(#374151, #cbd5e1);
    --nc-text-muted:      light-dark(#6b7280, #94a3b8);
    --nc-text-faint:      light-dark(#9ca3af, #64748b);
    --nc-text-on-badge:   light-dark(#1f2937, #e2e8f0);
    --nc-text-on-accent:  #ffffff;

    /* accents */
    --nc-accent:          #3b82f6;
    --nc-accent-strong:   #2563eb;
    --nc-danger:          #ef4444;
    --nc-disabled:        light-dark(#d1d5db, #475569);

    display: flex;
    flex-direction: column;
    height: calc(100vh - 6rem);
    background: var(--nc-bg-app);
    color: var(--nc-text-primary);
    border-radius: 0.5rem;
    overflow: hidden;
}

/* Honor Nova / Tailwind explicit theme toggles regardless of OS preference. */
:where(.dark, [data-theme='dark']) .nova-chat-root { color-scheme: dark; }
:where(.light, [data-theme='light']) .nova-chat-root { color-scheme: light; }

.nova-chat-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--nc-border);
    background: var(--nc-bg-surface);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.nova-chat-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: var(--nc-text-primary);
}

.nova-chat-loading,
.nova-chat-empty,
.nova-chat-placeholder {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--nc-text-muted);
    padding: 2rem;
    text-align: center;
}

.nova-chat-empty-hint {
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.nova-chat-empty-hint code {
    background: var(--nc-bg-bubble-other);
    color: var(--nc-text-primary);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.8125rem;
}

.nova-chat-body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(280px, 360px) 1fr;
    background: var(--nc-bg-surface);
}

@media (max-width: 768px) {
    .nova-chat-body {
        grid-template-columns: 1fr;
    }
}
</style>

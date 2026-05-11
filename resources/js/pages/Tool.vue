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

async function loadTopics() {
    try {
        const { data } = await Nova.request().get('/nova-vendor/nova-chat/topics')
        topics.value = data.data || []
        pollIntervals.value = data.config || pollIntervals.value
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
.nova-chat-root {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 6rem);
    background: var(--color-gray-50, #f9fafb);
    border-radius: 0.5rem;
    overflow: hidden;
}

.nova-chat-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--color-gray-200, #e5e7eb);
    background: #fff;
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
}

.nova-chat-loading,
.nova-chat-empty,
.nova-chat-placeholder {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--color-gray-500, #6b7280);
    padding: 2rem;
    text-align: center;
}

.nova-chat-empty-hint {
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.nova-chat-empty-hint code {
    background: var(--color-gray-100, #f3f4f6);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.8125rem;
}

.nova-chat-body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(280px, 360px) 1fr;
    background: #fff;
}

@media (max-width: 768px) {
    .nova-chat-body {
        grid-template-columns: 1fr;
    }
}
</style>

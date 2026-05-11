<template>
    <nav class="nova-chat-tabs">
        <button
            v-for="topic in topics"
            :key="topic.key"
            type="button"
            class="nova-chat-tab"
            :class="{ 'is-active': topic.key === active }"
            @click="$emit('change', topic)"
        >
            <span>{{ topic.label }}</span>
            <span v-if="topic.unread_count > 0" class="nova-chat-tab-badge">
                {{ topic.unread_count }}
            </span>
        </button>
    </nav>
</template>

<script setup>
defineProps({
    topics: { type: Array, required: true },
    active: { type: String, default: null },
})
defineEmits(['change'])
</script>

<style>
.nova-chat-tabs {
    display: flex;
    gap: 0.25rem;
}

.nova-chat-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: 9999px;
    background: var(--color-gray-100, #f3f4f6);
    color: var(--color-gray-700, #374151);
    font-size: 0.8125rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
}

.nova-chat-tab.is-active {
    background: var(--color-blue-500, #3b82f6);
    color: #fff;
}

.nova-chat-tab-badge {
    background: var(--color-red-500, #ef4444);
    color: #fff;
    border-radius: 9999px;
    padding: 0 0.4rem;
    font-size: 0.6875rem;
    min-width: 1.1rem;
    text-align: center;
}
</style>

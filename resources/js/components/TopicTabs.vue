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
    background: var(--nc-bg-tab);
    color: var(--nc-text-secondary);
    font-size: 0.8125rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: background-color 120ms ease, color 120ms ease;
}

.nova-chat-tab:hover {
    background: var(--nc-bg-hover);
}

.nova-chat-tab.is-active {
    background: var(--nc-accent);
    color: var(--nc-text-on-accent);
}

.nova-chat-tab-badge {
    background: var(--nc-danger);
    color: var(--nc-text-on-accent);
    border-radius: 9999px;
    padding: 0 0.4rem;
    font-size: 0.6875rem;
    min-width: 1.1rem;
    text-align: center;
}
</style>

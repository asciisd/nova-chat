<template>
    <form class="nova-chat-composer" @submit.prevent="send">
        <textarea
            ref="textareaEl"
            v-model="body"
            placeholder="Type a message…"
            rows="1"
            :disabled="sending"
            @keydown.enter.exact.prevent="send"
            @input="autoSize"
        />
        <button type="submit" :disabled="sending || !body.trim()">
            <span v-if="sending">…</span>
            <span v-else>Send</span>
        </button>
    </form>
</template>

<script setup>
import { nextTick, ref } from 'vue'

const props = defineProps({
    topicKey: { type: String, required: true },
    conversationId: { type: [Number, String], required: true },
})

const emit = defineEmits(['sent'])

const body = ref('')
const sending = ref(false)
const textareaEl = ref(null)

function autoSize() {
    const el = textareaEl.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 160) + 'px'
}

async function send() {
    const text = body.value.trim()
    if (!text || sending.value) return
    sending.value = true
    try {
        const { data } = await Nova.request().post(
            `/nova-vendor/nova-chat/topics/${props.topicKey}/conversations/${props.conversationId}/messages`,
            { body: text },
        )
        emit('sent', data.data || data)
        body.value = ''
        await nextTick()
        autoSize()
    } catch (e) {
        Nova.error(e?.response?.data?.message || 'Failed to send message.')
    } finally {
        sending.value = false
    }
}
</script>

<style>
.nova-chat-composer {
    border-top: 1px solid var(--color-gray-200, #e5e7eb);
    padding: 0.75rem;
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
    background: #fff;
}

.nova-chat-composer textarea {
    flex: 1;
    resize: none;
    border: 1px solid var(--color-gray-200, #e5e7eb);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    font-family: inherit;
    line-height: 1.4;
    background: var(--color-gray-50, #f9fafb);
    min-height: 2.5rem;
    max-height: 10rem;
}

.nova-chat-composer textarea:focus {
    outline: none;
    border-color: var(--color-blue-500, #3b82f6);
    background: #fff;
}

.nova-chat-composer button {
    padding: 0.5rem 1rem;
    background: var(--color-blue-500, #3b82f6);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    min-width: 4rem;
}

.nova-chat-composer button:disabled {
    background: var(--color-gray-300, #d1d5db);
    cursor: not-allowed;
}
</style>

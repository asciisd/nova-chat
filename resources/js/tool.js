import Tool from './pages/Tool.vue'

Nova.booting((app, store) => {
    Nova.inertia('NovaChat', Tool)
})

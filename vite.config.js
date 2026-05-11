import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: path.resolve(__dirname, 'dist/js'),
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: path.resolve(__dirname, 'resources/js/tool.js'),
            name: 'NovaChat',
            formats: ['iife'],
            fileName: () => 'tool.js',
            cssFileName: 'tool',
        },
        rollupOptions: {
            external: ['vue'],
            output: {
                globals: { vue: 'Vue' },
                assetFileNames: (asset) => (asset.name?.endsWith('.css') ? 'tool.css' : asset.name),
            },
        },
    },
})

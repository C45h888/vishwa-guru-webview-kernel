import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        svelte(),
    ],
    resolve: {
        alias: {
            $shared: resolve(__dirname, 'resources/js/shared'),
            $domains: resolve(__dirname, 'resources/js/domains'),
        },
    },
    build: {
        outDir: 'public/build',
        manifest: true,
    },
});

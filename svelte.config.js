import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/**
 * svelte.config.js — minimal Svelte configuration.
 *
 * Only `preprocess` is set; svelte-check is pinned to the project
 * root's vite.config.ts via the `--config` flag in the `check`
 * npm script (see package.json). Without the flag, svelte-check
 * auto-discovers Vite configs in vendor/laravel/breeze/stubs/ and
 * reports "No Svelte configuration found in vite config" errors
 * for the React/Vue Breeze installer stubs.
 */
export default {
    preprocess: vitePreprocess({ script: true }),
};

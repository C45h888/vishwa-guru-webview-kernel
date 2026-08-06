import '../css/app.css';
import { createInertiaApp } from '@inertiajs/svelte';
import { mount, type Component } from 'svelte';

/**
 * Page-discovery root, relative to this file (resources/js/app.ts).
 *
 * Must match `config('inertia.js_pages_root')` in config/inertia.php
 * (with the leading `./` stripped). The two are kept in lockstep by
 * InertiaPagesRootConsistencyTest in tests/Feature/Bootstrap/.
 *
 * Vite's `import.meta.glob` requires a literal string at build time
 * (it cannot resolve template-literal interpolations). So the glob
 * path uses the literal `./domains/**` while the runtime `resolvePage`
 * uses the constant. The constant stays the source of truth for
 * runtime; the literal must stay in sync with the constant.
 */
const PAGES_ROOT = './domains';

const pages = import.meta.glob<{ default: Component }>(
    './domains/**/*.svelte',
    { eager: false }
);

// Inertia 2's ComponentResolver is typed for Svelte 4's ComponentType; we run
// Svelte 5 components whose type is `Component<Props>`. The cast is a known
// compatibility shim — runtime semantics are correct.
const resolvePage = ((name: string) => {
    const path = `${PAGES_ROOT}/${name}.svelte`;
    const loader = pages[path];
    if (!loader) {
        throw new Error(`Inertia page not found: ${name} (looked for ${path})`);
    }
    return loader();
}) as unknown as Parameters<typeof createInertiaApp>[0]['resolve'];

createInertiaApp({
    resolve: resolvePage,
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element not found');
        }
        mount(App, { target: el, props });
    },
    progress: { color: '#000' },
});

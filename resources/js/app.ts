import '../css/app.css';
import { createInertiaApp } from '@inertiajs/svelte';
import { mount, type Component } from 'svelte';

const pages = import.meta.glob<{ default: Component }>(
    './domains/**/*.svelte',
    { eager: false }
);

// Inertia 2's ComponentResolver is typed for Svelte 4's ComponentType; we run
// Svelte 5 components whose type is `Component<Props>`. The cast is a known
// compatibility shim — runtime semantics are correct.
const resolvePage = ((name: string) => {
    const path = `./domains/${name}.svelte`;
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

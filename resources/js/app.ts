import '../css/app.css';
import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';

const pages = import.meta.glob<{ default: unknown }>(
    './domains/**/pages/*.svelte',
    { eager: false }
);

createInertiaApp({
    resolve: (name) => {
        const path = `./domains/${name}.svelte`;
        const loader = pages[path];
        if (!loader) {
            throw new Error(`Inertia page not found: ${name} (looked for ${path})`);
        }
        return loader();
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element not found');
        }
        mount(App, { target: el, props });
    },
    progress: { color: '#000' },
});

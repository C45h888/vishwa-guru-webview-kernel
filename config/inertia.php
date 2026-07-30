<?php

return [

    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
    ],

    'ensure_pages_exist' => false,

    /*
     * Page-discovery root (the directory under resources/js/ where Inertia
     * pages live). Single source of truth: the runtime and testing blocks
     * below both derive `page_paths` from this value, and the matching TS
     * constant in resources/js/app.ts (`const PAGES_ROOT = '...'`) is
     * asserted against this by InertiaPagesRootConsistencyTest.
     *
     * Override per-environment via the INERTIA_PAGES_ROOT env var.
     */
    'js_pages_root' => env('INERTIA_PAGES_ROOT', 'domains'),

    'page_paths' => [
        resource_path('js/' . env('INERTIA_PAGES_ROOT', 'domains')),
    ],

    'page_extensions' => [
        'js',
        'jsx',
        'svelte',
        'ts',
        'tsx',
        'vue',
    ],

    'use_script_element_for_initial_page' => (bool) env('INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE', false),

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/' . env('INERTIA_PAGES_ROOT', 'domains')),
        ],
        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

    'history' => [
        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),
    ],

];

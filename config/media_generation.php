<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Generated-Media Generation Pipeline (Flux2 on the InvokeAI pod)
    |--------------------------------------------------------------------------
    |
    | Wires the published Flux2 image-generation pipeline into the Cms
    | media kernel. The pipeline talks to a remote InvokeAI instance
    | (RTX 5090 pod) over its queue API and produces "publishable" images
    | that flow into the standard cms_media_assets / file_assets storage
    | surface (served via /media/{id}).
    |
    | The graph replicates the *proven* web-UI Flux2 run (generation_mode
    | `flux2_txt2img`): flux2_klein_model_loader → flux2_klein_text_encoder
    | → flux_kontext (reference underlay) → flux2_kontext_collect →
    | flux2_denoise → flux2_vae_decode.
    |
    | All values are env-driven so the endpoint + model keys + defaults
    | are swap-able between pods without a code change.
    |
    */

    'enabled' => env('MEDIA_GENERATION_ENABLED', false),

    // InvokeAI HTTP API root (pod). Includes scheme + host + port.
    'api_base_url' => env('MEDIA_GENERATION_API_URL', 'http://127.0.0.1:9090'),

    // The session-queue id InvokeAI processes promise updates against.
    'queue_id' => env('MEDIA_GENERATION_QUEUE', 'default'),

    // Minimum age (seconds) before we treat a promise as settled.
    'poll_timeout_seconds' => (int) env('MEDIA_GENERATION_POLL_TIMEOUT', 180),

    // InvokeAI image-store reference ingest category.
    'image_category' => env('MEDIA_GENERATION_IMAGE_CATEGORY', 'general'),

    /*
    |--------------------------------------------------------------------------
    | Flux2 model identifiers (must match the pod's registered models)
    |--------------------------------------------------------------------------
    */
    'flux2' => [
        'model' => [
            'key'  => env('MEDIA_GENERATION_FLUX2_MAIN_KEY', '2a53b1d0-bbb6-44f7-b33a-1ef9dc1f7cff'),
            'hash' => env('MEDIA_GENERATION_FLUX2_MAIN_HASH', 'blake3:c3ee838d71d99497db01fae6f304eafd9e734e935f3b783e968d50febb56be2c'),
            'name' => 'FLUX.2 Klein 4B (GGUF Q4)',
            'base' => 'flux2',
            'type' => 'main',
        ],
        'vae' => [
            'key'  => env('MEDIA_GENERATION_FLUX2_VAE_KEY', '45337d16-5b68-43f2-bca7-4e8771cbaa23'),
            'hash' => env('MEDIA_GENERATION_FLUX2_VAE_HASH', 'blake3:531855de70db993d0f6181f82cde27d15411d58b7ffa3b2fdce2b9434c0173c2'),
            'name' => 'FLUX.2 VAE',
            'base' => 'flux2',
            'type' => 'vae',
        ],
        'qwen3_encoder' => [
            'key'  => env('MEDIA_GENERATION_FLUX2_QWEN3_KEY', '5653091a-3e0f-4bf7-82e2-02d2997495d0'),
            'hash' => env('MEDIA_GENERATION_FLUX2_QWEN3_HASH', 'blake3:af5840e6770dc99f678e69867949c8b9264835915eb82a990e940fa6e4fa6c81'),
            'name' => 'FLUX.2 Klein Qwen3 4B Encoder',
            'base' => 'any',
            'type' => 'qwen3_encoder',
        ],
        // Prompt-context length the Qwen3 encoder supports (512 for GGUF Q4).
        'max_seq_len' => (int) env('MEDIA_GENERATION_FLUX2_MAX_SEQ_LEN', 512),

        // Default (and only, for 16:9 landscape hero capsules) output size.
        'width'  => (int) env('MEDIA_GENERATION_FLUX2_WIDTH', 1360),
        'height' => (int) env('MEDIA_GENERATION_FLUX2_HEIGHT', 768),

        // Diffusion defaults that preserve subject identity while allowing
        // angle/lighting recomposition (Kontext reference underlay).
        'steps'    => (int) env('MEDIA_GENERATION_FLUX2_STEPS', 30),
        'scheduler' => env('MEDIA_GENERATION_FLUX2_SCHEDULER', 'euler'),
        'cfg_scale' => (int) env('MEDIA_GENERATION_FLUX2_CFG', 5),
        'denoising_start' => (float) env('MEDIA_GENERATION_FLUX2_DENOISE_START', 0.0),
        'denoising_end'   => (float) env('MEDIA_GENERATION_FLUX2_DENOISE_END', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Photographic landscape prompt template (16:9)
    |--------------------------------------------------------------------------
    |
    | The subject/angle/lighting instruction is injected as {instruction};
    | the photographic-landscape tail (f/12, lens, lighting, contrast) is
    | constant and mirrors the web-UI template. This keeps renders
    | professional and 1:1 faithful to the source subjects (no AI gloss).
    |
    */
    'prompt_template' => env('MEDIA_GENERATION_PROMPT_TEMPLATE',
        '{instruction}, landscape photograph, f/12, lifelike, highly detailed. '
        . 'photography. architectural. natural shadows. cinematic warm tone. '
        . 'no airbrushing, no gloss, no alteration of subject identity.'
    ),
];
<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Contracts\MediaGenerationContract;
use App\Cms\Domain\Exceptions\SubjectProtectionRequiredException;
use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Shared\Exceptions\InfrastructureException;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Flux2ImageGenerationService — published Flux2 pipeline into the Cms
 * media kernel. Talks to the InvokeAI pod's queue API over HTTP and
 * re-renders a source image into a professional wide 16:9 landscape
 * while preserving subject identity 1:1.
 *
 * Pipeline (mirrors the proven web-UI run, generation_mode flux2_txt2img):
 *
 *   1. Authenticity guard — reject identity-critical subjects (faces/idols);
 *      they must use the structure-preserving upscale path instead.
 *   2. Upload the source image to InvokeAI's image store.
 *   3. Build the Flux2 graph using flux_kontext (the reference underlay) so
 *      subjects are re-rendered from an improved angle/lighting rather than
 *      re-invented.
 *   4. Enqueue + poll the session queue until the render settles.
 *   5. Download the rendered PNG, persist it to the `public` disk, and
 *      register file_assets + cms_media_assets so /media/{id} serves it.
 *
 * Everything external is env-driven via config/media_generation.php.
 *
 * @implements MediaGenerationContract
 */
final class Flux2ImageGenerationService implements MediaGenerationContract
{
    private const SOURCE_SUBJECT_TYPE = [
        'architecture' => 'allow_generate',
        'arcade' => 'allow_generate',
        'hall' => 'allow_generate',
        'interior' => 'allow_generate',
    ];

    public function __construct(
        private readonly SubjectAuthenticityClassifier $classifier,
        private readonly FileAssetRepositoryContract $fileAssets,
        private readonly CmsMediaAssetRepositoryContract $mediaAssets,
        private readonly array $config,
    ) {
    }

    /**
     * @param  string  $sourceImageBytes  raw source image bytes
     * @param  string  $instruction       subject/angle/lighting instruction
     * @param  string  $subjectSort       'architecture'|'interior'|'people'|'idol' (caller's declared sort)
     * @return array<string, mixed>
     * @throws SubjectProtectionRequiredException when faces/idols detected
     */
    public function rerenderWideShot(string $sourceImageBytes, string $instruction, string $subjectSort): array
    {
        $declared = strtolower($subjectSort);

        // ── 1. Authenticity guard (preemptive face protection) ──────
        // Never allow a subject-classification that walks over identity.
        $classification = $this->classifier->classify($declared, null);
        if (in_array($classification, ['preserve_face', 'preserve_idol'], true)) {
            throw SubjectProtectionRequiredException::forSource($declared, $classification);
        }

        if (! $this->cfg('enabled')) {
            throw new InfrastructureException(
                'cms.media_generation.disabled — Flux2 pipeline is not enabled (set MEDIA_GENERATION_ENABLED).',
                'cms.media-generation',
            );
        }

        $flux = $this->cfg('flux2');

        // ── 2. Upload source to InvokeAI image store ─────────────────
        $imageName = $this->uploadSource($sourceImageBytes);

        // ── 3. Build the Flux2 graph (kontext reference underlay) ────
        $graphId = 'flux2_kernel_'.Str::ulid();
        $nodes = [
            'positive_prompt' => [
                'id' => 'positive_prompt', 'type' => 'string',
                'value' => $this->renderPrompt($instruction),
            ],
            'source_image' => [
                'id' => 'source_image', 'type' => 'image',
                'image' => ['image_name' => $imageName],
            ],
            'model_loader' => [
                'id' => 'model_loader', 'type' => 'flux2_klein_model_loader',
                'model' => $flux['model'], 'vae_model' => $flux['vae'],
                'qwen3_encoder_model' => $flux['qwen3_encoder'],
                'max_seq_len' => (int) $flux['max_seq_len'],
            ],
            'text_encoder' => [
                'id' => 'text_encoder', 'type' => 'flux2_klein_text_encoder',
                'max_seq_len' => (int) $flux['max_seq_len'],
            ],
            'kontext' => [
                'id' => 'kontext', 'type' => 'flux_kontext',
                'image' => null, // wired below
            ],
            'kontext_collect' => [
                'id' => 'kontext_collect', 'type' => 'flux2_kontext_collect',
                'collection' => [],
            ],
            'denoise' => [
                'id' => 'denoise', 'type' => 'flux2_denoise',
                'cfg_scale' => (float) $flux['cfg_scale'],
                'num_steps' => (int) $flux['steps'],
                'scheduler' => $flux['scheduler'],
                'width' => (int) $flux['width'],
                'height' => (int) $flux['height'],
                'denoising_start' => (float) $flux['denoising_start'],
                'denoising_end' => (float) $flux['denoising_end'],
                'add_noise' => true,
            ],
            'decode' => [
                'id' => 'decode', 'type' => 'flux2_vae_decode',
            ],
        ];

        $edges = [
            // model → text encoder
            ['model_loader', 'qwen3_encoder', 'text_encoder', 'qwen3_encoder'],
            ['model_loader', 'max_seq_len', 'text_encoder', 'max_seq_len'],
            // prompt → text encoder
            ['positive_prompt', 'value', 'text_encoder', 'prompt'],
            // model → denoise
            ['model_loader', 'transformer', 'denoise', 'transformer'],
            ['model_loader', 'vae', 'denoise', 'vae'],
            // text encoder → denoise
            ['text_encoder', 'conditioning', 'denoise', 'positive_text_conditioning'],
            // source image → kontext (reference underlay)
            ['source_image', 'image', 'kontext', 'image'],
            // kontext → collect → denoise
            ['kontext', 'kontext_cond', 'kontext_collect', 'item'],
            ['kontext_collect', 'collection', 'denoise', 'kontext_conditioning'],
            // denoise → decode (+ vae)
            ['model_loader', 'vae', 'decode', 'vae'],
            ['denoise', 'latents', 'decode', 'latents'],
        ];

        $graph = [
            'id' => $graphId,
            'nodes' => $nodes,
            'edges' => array_map(static fn (array $e): array => [
                'source' => ['node_id' => $e[0], 'field' => $e[1]],
                'destination' => ['node_id' => $e[2], 'field' => $e[3]],
            ], $edges),
        ];

        $enqueue = $this->enqueue($graph);

        // ── 4. Poll the queue until render settles ───────────────────
        $resultImage = $this->awaitCompletion($enqueue['item_id']);

        // ── 5. Download + persist output via repositories ────────────
        return $this->persist($resultImage, $imageName, $instruction);
    }

    /**
     * Upload the source bytes to InvokeAI's image store.
     *
     * @return string  InvokeAI image_name (UUID.png)
     */
    private function uploadSource(string $bytes): string
    {
        $base = (string) $this->cfg('api_base_url');
        $category = (string) $this->cfg('image_category');
        $url = "{$base}/api/v1/images/upload?image_category={$category}&is_intermediate=false";

        $resp = Http::attach('file', $bytes, 'source.png', ['Content-Type' => 'image/png'])
            ->post($url);

        if ($resp->failed()) {
            throw new InfrastructureException(
                'cms.media_generation.upload_failed — '.($resp->body() ?? 'unknown'),
                'cms.media-generation',
            );
        }

        $imageName = (string) $resp->json()['image_name'];
        if ($imageName === '') {
            throw new InfrastructureException(
                'cms.media_generation.upload_empty — no image_name returned.',
                'cms.media-generation',
            );
        }

        return $imageName;
    }

    /**
     * Enqueue the Flux2 graph against InvokeAI's session queue.
     *
     * @return array<string, mixed>  { batch_id, item_ids, graph_id }
     */
    private function enqueue(array $graph): array
    {
        $base = (string) $this->cfg('api_base_url');
        $queue = (string) $this->cfg('queue_id');
        $url = "{$base}/api/v1/queue/{$queue}/enqueue_batch";

        $batch = [
            'batch_id' => (string) Str::ulid(),
            'graph' => $graph,
            'runs' => 1,
        ];

        $resp = Http::asJson()
            ->post($url, ['batch' => $batch, 'prepend' => false]);

        if ($resp->failed()) {
            throw new InfrastructureException(
                'cms.media_generation.enqueue_failed — HTTP '.($resp->status() ?? '?')
                .' '.($resp->body() ?? ''),
                'cms.media-generation',
            );
        }

        $json = $resp->json();
        $itemIds = (array) ($json['item_ids'] ?? []);

        return [
            'batch_id' => (string) ($json['batch_id'] ?? $batch['batch_id']),
            'item_id' => isset($itemIds[0]) ? (int) $itemIds[0] : -1,
            'graph_id' => (string) $graph['id'],
        ];
    }

    /**
     * Poll the queue until the item settles, returning the output image_name.
     *
     * @return string  InvokeAI image_name of the finished render
     */
    private function awaitCompletion(int $itemId): string
    {
        $base = (string) $this->cfg('api_base_url');
        $queue = (string) $this->cfg('queue_id');
        $timeout = (int) $this->cfg('poll_timeout_seconds');
        $elapsed = 0;
        $step = 3;

        while ($elapsed < $timeout) {
            sleep($step);
            $elapsed += $step;

            $url = "{$base}/api/v1/queue/{$queue}/i/{$itemId}";
            $resp = Http::get($url);

            if ($resp->failed()) {
                continue;
            }

            $status = (string) ($resp->json()['status'] ?? '');
            if ($status === 'completed') {
                return $this->extractOutputImage($resp->json(), $queue, $itemId);
            }
            if ($status === 'failed') {
                throw new InfrastructureException(
                    'cms.media_generation.render_failed — item '.$itemId.': '
                    .($resp->json()['error_message'] ?? 'unknown'),
                    'cms.media-generation',
                );
            }
        }

        throw new InfrastructureException(
            'cms.media_generation.render_timeout — item '.$itemId.' elapsed '.$timeout.'s.',
            'cms.media-generation',
        );
    }

    /**
     * Extract the final rendered image_name from a completed session.
     */
    private function extractOutputImage(array $item, string $queue, int $itemId): string
    {
        $session = (array) ($item['session'] ?? []);
        $results = (array) ($session['results'] ?? []);
        $base = (string) $this->cfg('api_base_url');

        // Find an image_output whose value carries an image_name.
        foreach ($results as $nodeId => $result) {
            if (is_array($result) && ($result['type'] ?? '') === 'image_output') {
                continue;
            }
            $value = $result['value'] ?? null;
            if (is_array($value)) {
                $name = (string) ($value['image_name'] ?? '');
                if ($name !== '') {
                    return $name;
                }
            }
        }

        // Fallback: query InvokeAI for the newest non-intermediate image for this session.
        return $this->newestSessionImage($queue, $itemId);
    }

    /**
     * Ask the images API for the most recent image of the completed session.
     *
     * @return string  image_name
     */
    private function newestSessionImage(string $queue, int $itemId): string
    {
        $base = (string) $this->cfg('api_base_url');
        $resp = Http::get("{$base}/api/v1/images/names");
        if ($resp->failed()) {
            throw new InfrastructureException(
                'cms.media_generation.image_lookup_failed.',
                'cms.media-generation',
            );
        }
        $names = (array) ($resp->json()['image_names'] ?? []);
        if (empty($names)) {
            throw new InfrastructureException(
                'cms.media_generation.no_output_image.',
                'cms.media-generation',
            );
        }

        return (string) $names[0];
    }

    /**
     * Download the rendered output and persist file_assets + cms_media_assets.
     *
     * @return array<string, mixed>
     */
    private function persist(string $outputImageName, string $sourceImageName, string $instruction): array
    {
        $base = (string) $this->cfg('api_base_url');
        $download = Http::get("{$base}/api/v1/images/i/{$outputImageName}/full");
        if ($download->failed()) {
            throw new InfrastructureException(
                'cms.media_generation.download_failed — image '.$outputImageName,
                'cms.media-generation',
            );
        }
        $bytes = $download->body();

        $hash = hash('sha256', $bytes);
        $size = strlen($bytes);

        // Physical file on the public disk, dedup by hash.
        $now = new DateTimeImmutable();
        $relativePath = sprintf(
            'generated-media/%s/%s/%s/%s.png',
            $now->format('Y'),
            $now->format('m'),
            substr((string) $hash, 0, 2),
            (string) $hash,
        );
        Storage::disk('public')->put($relativePath, $bytes);

        $fileAssetId = (string) Str::ulid();
        $fileRecord = new FileAssetRecord(
            id: $fileAssetId,
            ownerType: 'hero_banner',
            ownerId: 'generated',
            originalFilename: $outputImageName,
            storageDisk: 'public',
            storagePath: $relativePath,
            mimeType: 'image/png',
            fileSizeBytes: $size,
            fileHashSha256: (string) $hash,
            purpose: 'generated_hero',
            isPublic: true,
        );
        $this->fileAssets->save($fileRecord);

        $cmsId = (string) Str::ulid();
        $overlay = CmsMediaAssetRecord::create(
            id: $cmsId,
            fileAssetId: $fileAssetId,
            mediaType: PublicMediaType::HERO_DESKTOP,
            state: PublicMediaState::PUBLISHED,
            altText: $this->sanitizeAltText($instruction),
            caption: null,
            credit: 'AI-generated (Flux2)',
            width: (int) $this->cfg('flux2')['width'],
            height: (int) $this->cfg('flux2')['height'],
            publishedAt: $now,
            createdBy: 'media-generation',
            updatedBy: 'media-generation',
        );
        $this->mediaAssets->save($overlay);

        return [
            'cmsMediaAssetId' => $cmsId,
            'fileAssetId' => $fileAssetId,
            'width' => $overlay->width(),
            'height' => $overlay->height(),
            'bytes' => $bytes,
            'hash' => $hash,
        ];
    }

    /**
     * Build the photographic-landscape prompt from the config template.
     */
    private function renderPrompt(string $instruction): string
    {
        return str_replace(
            (string) $this->cfg('prompt_template'),
            '{instruction}',
            $instruction,
        );
    }

    private function sanitizeAltText(string $instruction): string
    {
        $clean = str_replace($instruction, [',', '"', "\n", "\r"], ' ');
        $clean = preg_replace('/\\s+/', ' ', $clean) ?? '';
        return trim(substr($clean, 0, 120));
    }

    private function cfg(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}
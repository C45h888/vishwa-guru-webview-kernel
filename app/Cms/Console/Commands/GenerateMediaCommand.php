<?php

declare(strict_types=1);

namespace App\Cms\Console\Commands;

use App\Cms\Contracts\MediaGenerationContract;
use App\Cms\Domain\Exceptions\SubjectProtectionRequiredException;
use App\Cms\Services\SubjectAuthenticityClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * php artisan temple:generate-media [--source=DIR] [--subject=architecture] [--instruction="..."]
 *
 * Runs the published Flux2 image-generation pass over source images in a
 * directory (default: the Cms media storage originals root), preserving
 * subject identity 1:1 and producing wide 16:9 landscape assets.
 *
 * Workflow (locked order):
 *   1. Homestead each source file, classify its subject.
 *   2. Face/idol subjects → refused by the authenticity guard and reported
 *      as preserved (must use the structure-preserving upscale path).
 *   3. Architecture/interior subjects → re-rendered through the Flux2
 *      pipeline and persisted into file_assets + cms_media_assets.
 *
 * Doctrine: the command is a thin shell over the service contract — it
 * never touches InvokeAI directly and never bypasses the authenticity
 * guard. It returns Command::FAILURE (1) if any image fails.
 */
final class GenerateMediaCommand extends Command
{
    protected $signature = 'temple:generate-media'
        . ' {--source= : Source directory (default: cms-media-originals on the public disk)}'
        . ' {--subject=architecture : Declared subject sort (architecture|interior|people|idol)}'
        . ' {--instruction= : Photographic instruction injected into the prompt template}'
        . ' {--dry-run : Classify + report without running generation}';

    protected $description = 'Run the Flux2 image-generation pass over Cms media source images (face-preserving).';

    public function __construct(
        private readonly MediaGenerationContract $generation,
        private readonly SubjectAuthenticityClassifier $classifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceDir = (string) ($this->option('source') ?: 'cms-media-originals');
        $subject = (string) ($this->option('subject') ?: 'architecture');
        $instruction = (string) ($this->option('instruction') ?:
            'wide angle view of the interior, improve the lighting and composition, '
            . 'keep every subject and architectural detail exactly as in the source');
        $dryRun = (bool) $this->option('dry-run');

        $disk = Storage::disk('public');

        $files = $this->collectImages($sourceDir);
        if (empty($files)) {
            $this->error("No candidate images found under {$sourceDir}");
            return Command::FAILURE;
        }

        $this->info(sprintf('Flux2 generation pass — %d source image(s), subject=%s', count($files), $subject));

        $completed = 0;
        $preserved = 0;
        $failed = 0;

        foreach ($files as $relativePath) {
            $name = basename($relativePath);
            $classification = $this->classifier->classify($name);

            if (in_array($classification, ['preserve_face', 'preserve_idol'], true)) {
                $this->warn(sprintf('PRESERVE [%s]: %s — refusing Flux2 re-render to keep identity.', $classification, $name));
                $preserved++;
                continue;
            }

            if ($dryRun) {
                $this->info(sprintf('DRY-RUN generate: %s (%s)', $name, $classification));
                $completed++;
                continue;
            }

            $this->info(sprintf('GENERATING: %s (%s)…', $name, $classification));
            try {
                $bytes = (string) $disk->get($relativePath);
                $result = $this->generation->rerenderWideShot($bytes, $instruction, $classification);
                $this->info(sprintf('  → cms_media_asset %s (%dx%d)', $result['cmsMediaAssetId'], $result['width'], $result['height']));
                $completed++;
            } catch (SubjectProtectionRequiredException $e) {
                $this->warn(sprintf('PRESERVE [%s]: %s — %s', $e->classification(), $name, $e->getMessage()));
                $preserved++;
            } catch (\Throwable $e) {
                $this->error(sprintf('FAILED: %s — %s', $name, $e->getMessage()));
                $failed++;
            }
        }

        $this->info(sprintf('Done: completed=%d preserved=%d failed=%d', $completed, $preserved, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function collectImages(string $sourceDir): array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($sourceDir) || ! $disk->directoryExists($sourceDir)) {
            return [];
        }

        $out = [];
        foreach ($disk->allFiles($sourceDir, true) as $rel) {
            $lower = strtolower($rel);
            if (preg_match('/\.(png|jpe?g|webp)$/', $lower)) {
                $out[] = $rel;
            }
        }

        return $out;
    }
}
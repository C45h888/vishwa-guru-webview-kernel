<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Codegen command — walks every PHP DTO / ValueObject and emits a
 * TypeScript interface file at resources/js/shared/lib/inertia.generated.ts.
 *
 * Single source of truth for wire shapes: PHP `toArray()` is canonical;
 * the hand-written inertia.ts becomes a shim that re-exports the generated
 * types plus shared infra (cn, formatMoney, percentOf).
 *
 * Run via:
 *   php artisan inertia:dump-types
 * Wired automatically into `composer post-autoload-dump` and `npm run prebuild`.
 */
final class InertiaDumpTypes extends Command
{
    protected $signature = 'inertia:dump-types';

    protected $description = 'Generate TypeScript types from PHP DTOs and ValueObjects';

    /**
     * Map of (interface_name => field_name => entity prefix) used to
     * brand TS id fields with `EntityId<'prefix'>` instead of `string`.
     * Mirrors App\Persistence\ValueObjects\EntityId::PATTERN prefixes.
     *
     * @var array<string, array<string, string>>
     */
    private const BRAND_MAP = [
        'CampaignSummaryProps'    => ['id' => 'campaign'],
        'CampaignDetailProps'     => ['id' => 'campaign'],
        'EventSummaryProps'       => ['id' => 'event'],
        'EventDetailProps'        => ['id' => 'event'],
        'GallerySummaryProps'     => ['id' => 'gallery'],
        'GalleryDetailProps'      => ['id' => 'gallery'],
        'GalleryImageProps'       => ['id' => 'gallery_image'],
        'PublicMediaProps'        => ['id' => 'public_media'],
        'ReceiptDraftProps'       => ['transaction_id' => 'payment'],
        'DonationIntentProps'     => ['campaign_id' => 'campaign'],
    ];

    public function handle(): int
    {
        $dtos = $this->discoverDtos();
        $ts = $this->emitTypeScript($dtos);
        $target = base_path('resources/js/shared/lib/inertia.generated.ts');
        file_put_contents($target, $ts);
        $this->info(sprintf('Wrote %d bytes to %s', strlen($ts), $target));

        return self::SUCCESS;
    }

    /**
     * @return list<class-string>
     */
    private function discoverDtos(): array
    {
        $patterns = [
            base_path('app/*/Domain/DTOs/*DTO.php'),
            base_path('app/*/Domain/ValueObjects/*.php'),
        ];

        $paths = [];
        foreach ($patterns as $pattern) {
            $found = glob($pattern) ?: [];
            $paths = array_merge($paths, $found);
        }
        $paths = array_values(array_unique($paths));

        return array_values(array_map(
            static fn(string $p): string => '\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                str_replace(base_path('app/'), 'App\\', $p)
            ),
            $paths,
        ));
    }

    /**
     * @param  list<class-string>  $dtos
     */
    private function emitTypeScript(array $dtos): string
    {
        $out = "// AUTO-GENERATED — do not edit by hand.\n";
        $out .= "// Regenerate via: php artisan inertia:dump-types\n";
        $out .= "// Source of truth: app/*/Domain/DTOs/*DTO.php + ValueObjects\n\n";
        $out .= "import type { EntityId } from './brand';\n\n";

        foreach ($dtos as $class) {
            $fixture = $this->loadFixture($class);
            if (empty($fixture)) {
                continue;
            }
            $shortName = (new \ReflectionClass($class))->getShortName();
            // Strip only the `DTO` suffix to map CampaignSummaryDTO → CampaignSummaryProps.
            // Don't strip `Draft` — ReceiptDraft (ValueObject) must stay ReceiptDraftProps.
            $tsName = preg_replace('/DTO$/', '', $shortName) . 'Props';
            $brands = self::BRAND_MAP[$tsName] ?? [];
            $out .= "export interface {$tsName} {\n";
            foreach ($fixture as $key => $value) {
                $tsType = isset($brands[$key])
                    ? "EntityId<'{$brands[$key]}'>"
                    : $this->tsType($value);
                $optional = $value === null && ! isset($brands[$key]) ? '?' : '';
                $out .= "    {$key}{$optional}: {$tsType};\n";
            }
            $out .= "}\n\n";
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $dtoClass): array
    {
        if (! class_exists($dtoClass)) {
            return [];
        }
        try {
            $reflection = new \ReflectionClass($dtoClass);
            $instance = $reflection->newInstanceWithoutConstructor();
            if (! method_exists($instance, 'toArray')) {
                return [];
            }
            return $instance->toArray();
        } catch (\Throwable) {
            // Some DTOs have strict `fromArray()` ctors and refuse to construct
            // with no args. Skip them rather than failing the whole codegen.
            return [];
        }
    }

    private function tsType(mixed $value): string
    {
        return match (true) {
            is_bool($value)                => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_array($value)               => 'Record<string, unknown>',
            $value === null                => 'unknown',
            default                        => 'string',
        };
    }
}
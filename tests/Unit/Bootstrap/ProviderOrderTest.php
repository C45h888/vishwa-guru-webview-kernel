<?php

declare(strict_types=1);

namespace Tests\Unit\Bootstrap;

use App\Campaigns\Providers\CampaignsServiceProvider;
use App\Cms\Providers\CmsServiceProvider;
use App\Events\Providers\EventsServiceProvider;
use App\Gallery\Providers\GalleryServiceProvider;
use App\Payments\Providers\PaymentsServiceProvider;
use App\Persistence\Providers\PersistenceServiceProvider;
use App\Queue\Providers\QueueServiceProvider;
use App\Redis\Providers\RedisServiceProvider;
use App\Runtime\Providers\RuntimeServiceProvider;
use App\Shared\Providers\SharedServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the doctrine ordering invariants documented at bootstrap/providers.php:17-35
 * to a hard test. The order encodes boot-time dependency invariants (Shared →
 * Persistence → Runtime → Redis → Queue → Payments → Cms → Campaigns → Gallery →
 * Events); reordering the canonical array silently breaks those invariants
 * because nothing else records them.
 *
 * This test fails fast if anyone reorders an entry without updating both the
 * doctrine comment AND the pinned-order list below.
 */
final class ProviderOrderTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function invariants(): array
    {
        return [
            [SharedServiceProvider::class,      PersistenceServiceProvider::class, 'Shared → Persistence'],
            [PersistenceServiceProvider::class, RuntimeServiceProvider::class,      'Persistence → Runtime'],
            [RuntimeServiceProvider::class,     RedisServiceProvider::class,         'Runtime → Redis'],
            [RedisServiceProvider::class,       QueueServiceProvider::class,         'Redis → Queue'],
            [QueueServiceProvider::class,       PaymentsServiceProvider::class,      'Queue → Payments'],
            [PaymentsServiceProvider::class,    CmsServiceProvider::class,           'Payments → Cms'],
            [CmsServiceProvider::class,         CampaignsServiceProvider::class,     'Cms → Campaigns'],
            [CampaignsServiceProvider::class,   GalleryServiceProvider::class,       'Campaigns → Gallery'],
            [GalleryServiceProvider::class,     EventsServiceProvider::class,        'Gallery → Events'],
        ];
    }

    public function test_canonical_provider_list_contains_required_entries(): void
    {
        $providers = require __DIR__.'/../../../bootstrap/providers.php';
        self::assertIsArray($providers, 'bootstrap/providers.php must return an array.');

        foreach (self::invariants() as [$before, $after, $description]) {
            self::assertContains(
                $before,
                $providers,
                "Doctrine invariant '{$description}': the canonical list must contain {$before}.",
            );
            self::assertContains(
                $after,
                $providers,
                "Doctrine invariant '{$description}': the canonical list must contain {$after}.",
            );
        }
    }

    public function test_doctrine_ordering_invariants_are_honored(): void
    {
        $providers = require __DIR__.'/../../../bootstrap/providers.php';
        self::assertIsArray($providers, 'bootstrap/providers.php must return an array.');

        foreach (self::invariants() as [$before, $after, $description]) {
            $iBefore = array_search($before, $providers, true);
            $iAfter = array_search($after, $providers, true);

            self::assertNotFalse($iBefore, "Canonical list must contain {$before}.");
            self::assertNotFalse($iAfter, "Canonical list must contain {$after}.");

            self::assertLessThan(
                $iAfter,
                $iBefore,
                "Doctrine invariant violated: {$description}. ".
                "{$before} must register before {$after} in bootstrap/providers.php.",
            );
        }
    }
}

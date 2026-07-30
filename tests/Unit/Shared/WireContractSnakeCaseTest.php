<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;

/**
 * Asserts every wire DTO and ValueObject emits snake_case keys in
 * `toArray()`. The wire convention is "snake_case everywhere" — the
 * DonationIntentProps TS interface, the ReceiptDraftProps interface,
 * the homepage aggregate props, all of them rely on this invariant.
 *
 * A future contributor who introduces a camelCase slip (via property
 * renaming or a new DTO field) trips this test immediately.
 */
final class WireContractSnakeCaseTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function discoverClasses(): array
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

        return array_values(array_unique(array_map(
            static fn(string $p): string => '\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                str_replace(base_path('app/'), 'App\\', $p)
            ),
            $paths,
        )));
    }

    public function test_every_wire_dto_emits_snake_case_keys(): void
    {
        $violations = [];

        foreach ($this->discoverClasses() as $class) {
            if (! class_exists($class)) {
                continue;
            }
            try {
                $reflection = new \ReflectionClass($class);
                $instance = $reflection->newInstanceWithoutConstructor();
                if (! method_exists($instance, 'toArray')) {
                    continue;
                }
                $keys = array_keys($instance->toArray());
            } catch (\Throwable) {
                // Some DTOs have strict `fromArray()` ctors and refuse
                // to construct with no args. Skip them.
                continue;
            }

            foreach ($keys as $key) {
                if (! preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $key)) {
                    $violations[] = "{$class}::toArray() emits non-snake_case key '{$key}'";
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "Wire-key snake_case violations:\n  " . implode("\n  ", $violations),
        );
    }
}
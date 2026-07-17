<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence\Neon\ValueObjects;

use App\Persistence\Neon\ValueObjects\NeonRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for NeonRole enum.
 */
final class NeonRoleTest extends TestCase
{
    #[Test]
    public function it_has_exactly_three_roles(): void
    {
        $this->assertCount(3, NeonRole::cases());
    }

    #[Test]
    public function the_expected_roles_are_present(): void
    {
        $values = array_map(fn ($r) => $r->value, NeonRole::cases());
        sort($values);

        $this->assertSame(['app', 'owner', 'reader'], $values);
    }

    #[Test]
    public function owner_requires_ddl(): void
    {
        $this->assertTrue(NeonRole::Owner->requiresDdl());
    }

    #[Test]
    public function app_does_not_require_ddl(): void
    {
        $this->assertFalse(NeonRole::App->requiresDdl());
    }

    #[Test]
    public function reader_does_not_require_ddl(): void
    {
        $this->assertFalse(NeonRole::Reader->requiresDdl());
    }

    #[Test]
    public function label_includes_descriptive_suffix(): void
    {
        $this->assertStringContainsString('DDL', NeonRole::Owner->label());
        $this->assertStringContainsString('read-write', NeonRole::App->label());
        $this->assertStringContainsString('read-only', NeonRole::Reader->label());
    }

    #[Test]
    public function tryFrom_parses_known_role_strings(): void
    {
        $this->assertSame(NeonRole::Owner, NeonRole::tryFrom('owner'));
        $this->assertSame(NeonRole::App, NeonRole::tryFrom('app'));
        $this->assertSame(NeonRole::Reader, NeonRole::tryFrom('reader'));
    }

    #[Test]
    public function tryFrom_returns_null_for_unknown_values(): void
    {
        $this->assertNull(NeonRole::tryFrom('admin'));
        $this->assertNull(NeonRole::tryFrom(''));
    }
}
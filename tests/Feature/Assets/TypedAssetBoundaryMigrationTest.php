<?php

declare(strict_types=1);

namespace Tests\Feature\Assets;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TypedAssetBoundaryMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function typed_asset_tables_and_sqlite_boundary_triggers_exist(): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('cms_media_assets'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('payment_document_assets'));

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite trigger assertion only');
        }

        $triggers = DB::select("SELECT name FROM sqlite_master WHERE type = 'trigger' AND (name LIKE '%_cms_boundary_%' OR name LIKE '%_payment_boundary_%')");
        $this->assertCount(16, $triggers);
    }
}

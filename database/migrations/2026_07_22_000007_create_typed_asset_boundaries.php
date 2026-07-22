<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresTypedTables();
            $this->addPostgresBoundaryConstraints();
            return;
        }

        Schema::create('cms_media_assets', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('file_asset_id')->unique();
            $table->enum('media_type', [
                'hero_desktop', 'hero_mobile', 'campaign_cover', 'event_banner',
                'gallery_cover', 'gallery_image', 'content_block_image',
                'social_share_image', 'temple_logo', 'trust_seal',
            ]);
            $table->enum('state', ['draft', 'ready', 'published', 'archived'])->default('draft');
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('focal_x', 5, 4)->nullable();
            $table->decimal('focal_y', 5, 4)->nullable();
            $table->string('variant_group_id')->nullable()->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->foreign('file_asset_id')->references('id')->on('file_assets')->restrictOnDelete();
        });

        Schema::create('payment_document_assets', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('file_asset_id')->unique();
            $table->enum('document_type', [
                'donation_proof', 'receipt', 'certificate_80g',
                'refund_evidence', 'gateway_evidence',
            ]);
            $table->enum('state', ['pending', 'generated', 'issued', 'superseded', 'archived'])->default('pending');
            $table->string('donation_id')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('receipt_id')->nullable();
            $table->enum('access_classification', ['private', 'donor', 'staff', 'audit'])->default('private');
            $table->timestampTz('immutable_at')->nullable();
            $table->timestampTz('retention_until')->nullable();
            $table->string('superseded_by_id')->nullable();
            $table->timestampsTz();
            $table->timestampTz('archived_at')->nullable();
            $table->softDeletesTz();
            $table->foreign('file_asset_id')->references('id')->on('file_assets')->restrictOnDelete();
            $table->foreign('donation_id')->references('id')->on('donations')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('receipt_id')->references('id')->on('receipts')->restrictOnDelete();
            $table->foreign('superseded_by_id')->references('id')->on('payment_document_assets')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->addPostgresBoundaryConstraints();
        } elseif (DB::getDriverName() === 'sqlite') {
            $this->addSqliteBoundaryTriggers();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->dropPostgresBoundaryConstraints();
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach ($this->sqliteTriggerNames() as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }

        Schema::dropIfExists('payment_document_assets');
        Schema::dropIfExists('cms_media_assets');
    }

    private function createPostgresTypedTables(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS cms_media_assets (
                id TEXT PRIMARY KEY,
                file_asset_id TEXT NOT NULL UNIQUE REFERENCES file_assets(id) ON DELETE RESTRICT,
                media_type TEXT NOT NULL CHECK (media_type IN ('hero_desktop','hero_mobile','campaign_cover','event_banner','gallery_cover','gallery_image','content_block_image','social_share_image','temple_logo','trust_seal')),
                state TEXT NOT NULL DEFAULT 'draft' CHECK (state IN ('draft','ready','published','archived')),
                alt_text TEXT,
                caption TEXT,
                credit TEXT,
                width INTEGER CHECK (width IS NULL OR width > 0),
                height INTEGER CHECK (height IS NULL OR height > 0),
                focal_x NUMERIC(5,4),
                focal_y NUMERIC(5,4),
                variant_group_id TEXT,
                published_at TIMESTAMPTZ,
                archived_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                deleted_at TIMESTAMPTZ,
                created_by TEXT,
                updated_by TEXT,
                CONSTRAINT cms_media_publish_metadata CHECK (state <> 'published' OR (published_at IS NOT NULL AND alt_text IS NOT NULL)),
                CONSTRAINT cms_media_archive_timestamp CHECK (state <> 'archived' OR archived_at IS NOT NULL)
            )
        SQL);
        DB::statement('CREATE INDEX IF NOT EXISTS cms_media_variant_group_idx ON cms_media_assets (variant_group_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS payment_document_assets (
                id TEXT PRIMARY KEY,
                file_asset_id TEXT NOT NULL UNIQUE REFERENCES file_assets(id) ON DELETE RESTRICT,
                document_type TEXT NOT NULL CHECK (document_type IN ('donation_proof','receipt','certificate_80g','refund_evidence','gateway_evidence')),
                state TEXT NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','generated','issued','superseded','archived')),
                donation_id TEXT REFERENCES donations(id) ON DELETE RESTRICT,
                payment_id TEXT REFERENCES payments(id) ON DELETE RESTRICT,
                receipt_id TEXT REFERENCES receipts(id) ON DELETE RESTRICT,
                access_classification TEXT NOT NULL DEFAULT 'private' CHECK (access_classification IN ('private','donor','staff','audit')),
                immutable_at TIMESTAMPTZ,
                retention_until TIMESTAMPTZ,
                superseded_by_id TEXT REFERENCES payment_document_assets(id) ON DELETE RESTRICT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                archived_at TIMESTAMPTZ,
                deleted_at TIMESTAMPTZ,
                CONSTRAINT payment_document_issued_immutable CHECK (state <> 'issued' OR immutable_at IS NOT NULL),
                CONSTRAINT payment_document_retention_valid CHECK (retention_until IS NULL OR immutable_at IS NULL OR retention_until >= immutable_at)
            )
        SQL);
    }

    private function addPostgresBoundaryConstraints(): void
    {
        $constraints = [
            ['hero_banners', 'image_file_id', 'hero_banners_image_cms_boundary_fk', 'cms_media_assets'],
            ['hero_banners', 'mobile_image_file_id', 'hero_banners_mobile_image_cms_boundary_fk', 'cms_media_assets'],
            ['campaigns', 'cover_image_file_id', 'campaigns_cover_cms_boundary_fk', 'cms_media_assets'],
            ['events', 'banner_file_id', 'events_banner_cms_boundary_fk', 'cms_media_assets'],
            ['galleries', 'cover_image_file_id', 'galleries_cover_cms_boundary_fk', 'cms_media_assets'],
            ['gallery_images', 'file_asset_id', 'gallery_images_cms_boundary_fk', 'cms_media_assets'],
            ['receipts', 'receipt_file_id', 'receipts_file_payment_boundary_fk', 'payment_document_assets'],
            ['receipts', 'certificate_80g_file_id', 'receipts_80g_payment_boundary_fk', 'payment_document_assets'],
        ];

        foreach ($constraints as [$table, $column, $name, $target]) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$target}(id) ON DELETE RESTRICT");
        }
    }

    private function dropPostgresBoundaryConstraints(): void
    {
        foreach ([
            ['hero_banners', 'hero_banners_image_cms_boundary_fk'],
            ['hero_banners', 'hero_banners_mobile_image_cms_boundary_fk'],
            ['campaigns', 'campaigns_cover_cms_boundary_fk'],
            ['events', 'events_banner_cms_boundary_fk'],
            ['galleries', 'galleries_cover_cms_boundary_fk'],
            ['gallery_images', 'gallery_images_cms_boundary_fk'],
            ['receipts', 'receipts_file_payment_boundary_fk'],
            ['receipts', 'receipts_80g_payment_boundary_fk'],
        ] as [$table, $name]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }
    }

    private function addSqliteBoundaryTriggers(): void
    {
        $rules = [
            ['hero_banners', 'image_file_id', 'hero_banners_image_cms_boundary'],
            ['hero_banners', 'mobile_image_file_id', 'hero_banners_mobile_image_cms_boundary'],
            ['campaigns', 'cover_image_file_id', 'campaigns_cover_cms_boundary'],
            ['events', 'banner_file_id', 'events_banner_cms_boundary'],
            ['galleries', 'cover_image_file_id', 'galleries_cover_cms_boundary'],
            ['gallery_images', 'file_asset_id', 'gallery_images_cms_boundary'],
        ];
        foreach ($rules as [$table, $column, $name]) {
            DB::statement("CREATE TRIGGER {$name}_insert BEFORE INSERT ON {$table} WHEN NEW.{$column} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM cms_media_assets WHERE id = NEW.{$column}) BEGIN SELECT RAISE(ABORT, 'cms media boundary violation'); END");
            DB::statement("CREATE TRIGGER {$name}_update BEFORE UPDATE OF {$column} ON {$table} WHEN NEW.{$column} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM cms_media_assets WHERE id = NEW.{$column}) BEGIN SELECT RAISE(ABORT, 'cms media boundary violation'); END");
        }
        foreach ([
            ['receipt_file_id', 'receipts_file_payment_boundary'],
            ['certificate_80g_file_id', 'receipts_80g_payment_boundary'],
        ] as [$column, $name]) {
            DB::statement("CREATE TRIGGER {$name}_insert BEFORE INSERT ON receipts WHEN NEW.{$column} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payment_document_assets WHERE id = NEW.{$column}) BEGIN SELECT RAISE(ABORT, 'payment document boundary violation'); END");
            DB::statement("CREATE TRIGGER {$name}_update BEFORE UPDATE OF {$column} ON receipts WHEN NEW.{$column} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payment_document_assets WHERE id = NEW.{$column}) BEGIN SELECT RAISE(ABORT, 'payment document boundary violation'); END");
        }
    }

    /** @return list<string> */
    private function sqliteTriggerNames(): array
    {
        $bases = [
            'hero_banners_image_cms_boundary', 'hero_banners_mobile_image_cms_boundary',
            'campaigns_cover_cms_boundary', 'events_banner_cms_boundary',
            'galleries_cover_cms_boundary', 'gallery_images_cms_boundary',
            'receipts_file_payment_boundary', 'receipts_80g_payment_boundary',
        ];
        $names = [];
        foreach ($bases as $base) {
            $names[] = $base.'_insert';
            $names[] = $base.'_update';
        }
        return $names;
    }
};

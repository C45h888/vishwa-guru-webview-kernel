<?php

declare(strict_types=1);

namespace Tests\Feature\Phase1;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * trust:replace-email against the SQLite test schema only.
 */
final class ReplaceTrustEmailCommandTest extends InfrastructureTestCase
{
    private const OLD = 'Old.Personal@gmail.com';

    private const NEW = 'sriramguruji@vsrsms.in';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('contact_information')->insert([
            'id' => '01JTESTCONTACT000000000001',
            'label' => 'Email',
            'contact_type' => 'email',
            'value' => strtolower(self::OLD),
            'is_primary' => true,
            'display_order' => 0,
            'metadata' => '{}',
        ]);

        DB::table('static_pages')->insert([
            'id' => '01JTESTPAGE000000000000001',
            'slug' => 'privacy',
            'title' => 'Privacy',
            'body_json' => '{}',
            'body_html' => '<p>Write to '.self::OLD.' for help.</p>',
            'state' => 'published',
            'legal_page_content' => json_encode(['contact' => ['email' => self::OLD]]),
        ]);
    }

    public function test_requires_old_address(): void
    {
        $this->artisan('trust:replace-email')->assertExitCode(2);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('trust:replace-email', ['--from' => self::OLD, '--dry-run' => true])->assertExitCode(0);

        $this->assertSame(strtolower(self::OLD), DB::table('contact_information')->value('value'));
    }

    public function test_detect_lists_gmail_addresses(): void
    {
        $this->artisan('trust:replace-email', ['--detect' => true])
            ->expectsOutputToContain(strtolower(self::OLD))
            ->assertExitCode(0);
    }

    public function test_replaces_everywhere_and_is_idempotent(): void
    {
        $this->artisan('trust:replace-email', ['--from' => self::OLD])->assertExitCode(0);

        $this->assertSame(self::NEW, DB::table('contact_information')->value('value'));
        $page = DB::table('static_pages')->where('slug', 'privacy')->first();
        $this->assertStringContainsString(self::NEW, $page->body_html);
        $this->assertStringNotContainsStringIgnoringCase(self::OLD, $page->body_html);
        $this->assertSame(self::NEW, json_decode($page->legal_page_content, true)['contact']['email']);
        $this->assertSame(self::NEW, DB::table('trust_identities')->where('key', 'canonical')->value('email'));

        // Second run: nothing left to change.
        $this->artisan('trust:replace-email', ['--from' => self::OLD])
            ->expectsOutputToContain('Updated 0 value(s).')
            ->assertExitCode(0);
    }
}

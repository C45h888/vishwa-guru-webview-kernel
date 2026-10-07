<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * trust:replace-email — swap the trust's old public email address for the
 * new one across the DB-plane content (Phase 1).
 *
 * The old personal address is NOT in the repository or seeders — it only
 * exists in production data — so it is a required parameter (`--from`,
 * or env TRUST_OLD_EMAIL). Use `--detect` first to list every gmail.com
 * address present in the scanned columns.
 *
 * Idempotent: rows are selected with a LIKE on the old address, so a
 * second run finds nothing and changes nothing. Case-insensitive match;
 * JSON columns are string-replaced on their serialized form (an email
 * address contains no JSON-escaped characters, so this is safe).
 *
 * Scanned surfaces (from the migrations):
 *   - contact_information.value / metadata
 *   - static_pages: title, meta_description, body_json, body_html,
 *     seo_metadata, homepage_content, about_page_content, legal_page_content
 *   - trust_identities.email (key=canonical) — also filled when NULL,
 *     so receipts print the new address.
 */
final class ReplaceTrustEmail extends Command
{
    protected $signature = 'trust:replace-email
        {--from= : Old email address to replace (or env TRUST_OLD_EMAIL)}
        {--to= : New email address (defaults to config trust.email)}
        {--dry-run : Report what would change without writing}
        {--detect : Only list gmail.com addresses found in the scanned columns}';

    protected $description = 'Idempotently replace the old trust email with the new one in contact settings, CMS/legal content and trust identity.';

    /** @var array<string, list<string>> */
    private const TARGETS = [
        'contact_information' => ['value', 'metadata'],
        'static_pages' => [
            'title', 'meta_description', 'body_json', 'body_html', 'seo_metadata',
            'homepage_content', 'about_page_content', 'legal_page_content',
        ],
    ];

    public function handle(): int
    {
        if ($this->option('detect')) {
            return $this->detect();
        }

        $from = trim((string) ($this->option('from') ?: env('TRUST_OLD_EMAIL', '')));
        $to = trim((string) ($this->option('to') ?: config('trust.email', '')));
        $dry = (bool) $this->option('dry-run');

        if ($from === '' || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide the old address with --from=<email> (or TRUST_OLD_EMAIL). Run with --detect to find candidates.');

            return self::INVALID;
        }

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid --to address.');

            return self::INVALID;
        }

        if (strcasecmp($from, $to) === 0) {
            $this->info('Old and new addresses are identical; nothing to do.');

            return self::SUCCESS;
        }

        $total = 0;
        $pattern = '/'.preg_quote($from, '/').'/i';

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $this->line("skip {$table}: table missing");

                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $rows = DB::table($table)
                    ->select(['id', $column])
                    ->whereRaw('LOWER(CAST('.$this->q($column).' AS TEXT)) LIKE ?', ['%'.strtolower($from).'%'])
                    ->get();

                foreach ($rows as $row) {
                    $old = (string) $row->{$column};
                    $new = (string) preg_replace($pattern, $to, $old);

                    if ($new === $old) {
                        continue;
                    }

                    $total++;
                    $this->line(($dry ? '[dry-run] ' : '')."{$table}.{$column} id={$row->id}");

                    if (! $dry) {
                        DB::table($table)->where('id', $row->id)->update([$column => $new]);
                    }
                }
            }
        }

        $total += $this->updateTrustIdentity($from, $to, $dry);

        if (! $dry && $total > 0) {
            // Resolved CMS pages / sitemap are cached; drop them so the
            // new address is visible immediately.
            try {
                Cache::flush();
            } catch (\Throwable) {
                $this->warn('Cache flush failed; cached pages may show the old address until TTL expiry.');
            }
        }

        $this->info(($dry ? 'Would update ' : 'Updated ').$total.' value(s).');

        return self::SUCCESS;
    }

    private function updateTrustIdentity(string $from, string $to, bool $dry): int
    {
        if (! Schema::hasTable('trust_identities')) {
            return 0;
        }

        $row = DB::table('trust_identities')->where('key', 'canonical')->first();

        if ($row === null) {
            return 0;
        }

        $current = trim((string) ($row->email ?? ''));

        if ($current !== '' && strcasecmp($current, $from) !== 0) {
            return 0; // already set to something else (incl. the new address)
        }

        $this->line(($dry ? '[dry-run] ' : '').'trust_identities.email key=canonical');

        if (! $dry) {
            DB::table('trust_identities')->where('key', 'canonical')->update(['email' => $to]);
        }

        return 1;
    }

    private function detect(): int
    {
        $found = [];

        foreach (self::TARGETS + ['trust_identities' => ['email']] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $values = DB::table($table)
                    ->whereRaw('LOWER(CAST('.$this->q($column).' AS TEXT)) LIKE ?', ['%@gmail.com%'])
                    ->pluck($column);

                foreach ($values as $value) {
                    preg_match_all('/[A-Z0-9._%+-]+@gmail\.com/i', (string) $value, $m);
                    foreach ($m[0] as $addr) {
                        $found[strtolower($addr)][] = "{$table}.{$column}";
                    }
                }
            }
        }

        if ($found === []) {
            $this->info('No gmail.com addresses found.');

            return self::SUCCESS;
        }

        foreach ($found as $addr => $where) {
            $this->line($addr.'  ->  '.implode(', ', array_unique($where)));
        }

        return self::SUCCESS;
    }

    private function q(string $column): string
    {
        return DB::getQueryGrammar()->wrap($column);
    }
}

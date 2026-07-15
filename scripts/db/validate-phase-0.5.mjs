#!/usr/bin/env node
// scripts/db/validate-phase-0.5.mjs
//
// Phase 0.5 Database Validation harness.
//
// Drives happy-path INSERTs + negative-path probes + cascade/restrict probes
// against the deployed schema (currently on the linked `phase-0.5-db` Neon
// branch). Every negative probe is wrapped in a SAVEPOINT so a single CHECK
// violation does NOT abort the whole run.
//
// Setup:
//   cd /tmp && npm install --no-save pg
//   export PHASE_URL='postgresql://neondb_owner:npg_…@ep-…c-2.<region>.aws.neon.tech/neondb?sslmode=require'
//   node scripts/db/validate-phase-0.5.mjs
//
// Exits 0 if every probe passes; non-zero otherwise.

import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import pg from 'file:///tmp/node_modules/pg/esm/index.mjs';

const { Client } = pg;

// ─────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────

const conn = process.env.PHASE_URL;
if (!conn) {
  console.error('ERROR: PHASE_URL env var is required.');
  console.error('  Get it via:  npx -y neon@latest connection-string phase-0.5-db --pretty');
  console.error('  Then:        export PHASE_URL="postgresql://…"');
  process.exit(2);
}

const ULID_LEN = 26;
const randSuffix = () => crypto.randomBytes(10).toString('hex').slice(0, ULID_LEN - 10);
const id = (prefix) => `${prefix}_${Date.now().toString(36).toLowerCase().padStart(8, '0')}${randSuffix().slice(0, ULID_LEN - 10)}`;
const sha256hex = (s) => crypto.createHash('sha256').update(s).digest('hex');
const NOW_ISO = () => new Date().toISOString();
const PAST = (mins) => new Date(Date.now() - mins * 60_000).toISOString();
const FUTURE = (mins) => new Date(Date.now() + mins * 60_000).toISOString();

// Process-local generated IDs so probe results can refer back to the rows
// they targeted.
const ID = {
  currency_inr: 'INR',
  currency_usd: 'USD',
  currency_eur: 'EUR',
  provider_razorpay: 'razorpay',
  provider_paypal: 'paypal',
  donor_full: id('donr'),
  donor_anon: id('donr'),
  donor_no_contact: id('donr'),
  campaign_main: id('cmp'),
  campaign_orphan: id('cmp'),
  file_receipt: id('file'),
  file_80g: id('file'),
  ik_donation: 'ik_donation_' + crypto.randomBytes(8).toString('hex'),
  ik_payment: 'ik_payment_' + crypto.randomBytes(8).toString('hex'),
  donation_main: id('don'),
  payment_main: id('pay'),
  receipt_main: id('rec'),
  staticpage_main: id('page'),
  staticpage_other: id('page'),
  staticpage_softdeleted: id('page'),
  staticpage_slugreused: id('page'),
  contact_main: id('cont'),
  hero_banner: id('herob'),
  staticpage_with_banner: id('page'),
  webhook_event_1: id('whe'),
  webhook_event_2_dup: id('whe'),
  failure_state: id('fail'),
};

// ─────────────────────────────────────────────────────────────────────
// Probe runner
// ─────────────────────────────────────────────────────────────────────

const probes = [];

function record(p) {
  probes.push({ ran_at: NOW_ISO(), ...p });
  const tag = p.status === 'pass' ? '\x1b[32m✓\x1b[0m' : p.status === 'fail' ? '\x1b[31m✗\x1b[0m' : '\x1b[33m·\x1b[0m';
  const name = p.name.padEnd(58);
  const detail =
    p.expected_sqlstate ? ` expect=${p.expected_sqlstate}` :
    p.kind === 'cascade' ? ` expected=${p.expected}` :
    '';
  const got =
    p.observed_sqlstate ? ` got=${p.observed_sqlstate}` :
    p.kind === 'cascade' && p.cascade_rows_removed !== undefined ? ` cascade_rows=${p.cascade_rows_removed}` :
    '';
  console.log(`  ${tag} ${p.id.padEnd(5)} ${name}${detail}${got}${p.note ? '  // ' + p.note : ''}`);
}

async function expectViolation(client, id_, name, sql, expected, args = []) {
  await client.query('BEGIN');
  try {
    await client.query(sql, args);
    await client.query('COMMIT');
    record({ id: id_, name, kind: 'negative', status: 'fail', expected_sqlstate: expected, note: 'expected violation but INSERT succeeded' });
    return false;
  } catch (e) {
    await client.query('ROLLBACK');
    const pass = e.code === expected;
    record({
      id: id_, name, kind: 'negative', status: pass ? 'pass' : 'fail',
      expected_sqlstate: expected, observed_sqlstate: e.code,
      ...(pass ? {} : { error: e.message }),
    });
    return pass;
  }
}

async function expectSuccess(client, id_, name, sql, args = []) {
  await client.query('BEGIN');
  try {
    await client.query(sql, args);
    await client.query('COMMIT');
    record({ id: id_, name, kind: 'happy', status: 'pass' });
    return true;
  } catch (e) {
    await client.query('ROLLBACK');
    record({ id: id_, name, kind: 'happy', status: 'fail', observed_sqlstate: e.code, error: e.message });
    return false;
  }
}

async function expectBehavior(client, id_, name, kind, behavior, fn) {
  await client.query('BEGIN');
  try {
    const out = await fn();
    await client.query('COMMIT');
    record({ id: id_, name, kind, status: 'pass', expected: behavior, ...(out ?? {}) });
    return true;
  } catch (e) {
    await client.query('ROLLBACK');
    record({ id: id_, name, kind, status: 'fail', expected: behavior, error: e.message });
    return false;
  }
}

// ─────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────

const client = new Client({ connectionString: conn });
const branch = process.env.NEON_BRANCH || 'phase-0.5-db (from .neon)';

console.log(`\n=== Phase 0.5 schema validation on branch ${branch} ===\n`);

try {
  await client.connect();

  // ── 1. Reset state ────────────────────────────────────────────────
  console.log('Resetting validation state (TRUNCATE … CASCADE)…');
  await client.query(`TRUNCATE TABLE
    audit_events, notifications, webhook_events,
    idempotency_keys, failure_states,
    receipts, payments, donations,
    gallery_images, galleries, events,
    static_page_references, hero_banner_pages, hero_banners, static_pages, contact_information,
    campaigns, donors, file_assets, payment_providers, currencies
    CASCADE`);

  // ── 2. Happy-path chain ───────────────────────────────────────────
  console.log('\nHAPPY-PATH chain:');

  // 2.1 currencies (lookup data)
  await expectSuccess(client, 'h1', 'currencies — INR/USD/EUR',
    `INSERT INTO currencies(code,name,symbol,minor_unit_digits)
     VALUES ('INR','Indian Rupee','₹',2),
            ('USD','US Dollar','$',2),
            ('EUR','Euro','€',2)`);

  // 2.2 payment_providers
  await expectSuccess(client, 'h2', 'payment_providers — razorpay + paypal',
    `INSERT INTO payment_providers(code,display_name,priority,supported_currencies,min_amount_minor,max_amount_minor)
     VALUES ('razorpay','Razorpay',10,ARRAY['INR']::char(3)[],100,10000000),
            ('paypal','PayPal',20,ARRAY['USD','EUR']::char(3)[],100,10000000)`);

  // 2.3 donors (one full, one anonymous)
  await expectSuccess(client, 'h3', 'donors — full PII + anonymized',
    `INSERT INTO donors(id,full_name,email,phone,country_code,city,is_anonymized)
     VALUES ($1,'Anita Sharma','anita@example.com','+919876543210','IN','Bengaluru',FALSE),
            ($2,NULL,NULL,NULL,NULL,NULL,TRUE)`,
    [ID.donor_full, ID.donor_anon]);

  // 2.4 campaigns
  await expectSuccess(client, 'h4', 'campaign — active, INR',
    `INSERT INTO campaigns(id,slug,title,short_description,category,currency_code,state,is_featured)
     VALUES ($1,'temple-maintenance-2025','Temple Maintenance 2025','Annual upkeep','maintenance','INR','active',TRUE)`,
    [ID.campaign_main]);

  // 2.5 idempotency_keys (must exist before donations/payments reference them)
  await expectSuccess(client, 'h5', 'idempotency_keys — donation + payment',
    `INSERT INTO idempotency_keys(key,scope,request_fingerprint,expires_at)
     VALUES ($1,'donation_create','fp_don_001',$3),
            ($2,'payment_init','fp_pay_001',$3)`,
    [ID.ik_donation, ID.ik_payment, FUTURE(60)]);

  // 2.6 donations — non-anonymous with full snapshots
  await expectSuccess(client, 'h6', 'donation — non-anonymous, INR 1000, completed',
    `INSERT INTO donations(id,campaign_id,donor_id,
       donor_name_snapshot,donor_email_snapshot,donor_phone_snapshot,
       amount_minor,currency_code,is_anonymous,state,idempotency_key,
       submitted_at,payment_verified_at,receipt_generated_at,completed_at)
     VALUES ($1,$2,$3,
       'Anita Sharma','anita@example.com','+919876543210',
       100000,'INR',FALSE,'completed',$4,
       $5,$5,$5,$5)`,
    [ID.donation_main, ID.campaign_main, ID.donor_full, ID.ik_donation, PAST(2)]);

  // 2.7 payments — captured (will be settled later)
  await expectSuccess(client, 'h7', 'payment — captured INR 1000',
    `INSERT INTO payments(id,donation_id,provider_code,
       provider_order_id,provider_payment_id,
       amount_minor,currency_code,amount_captured_minor,amount_refunded_minor,
       status,idempotency_key,
       initiated_at,authorized_at,captured_at,verified_at)
     VALUES ($1,$2,'razorpay',
       'order_TEST_001','pay_TEST_001',
       100000,'INR',100000,0,
       'captured',$3,
       $4,$4,$4,$4)`,
    [ID.payment_main, ID.donation_main, ID.ik_payment, PAST(1)]);

  // 2.8 file_assets (must exist before receipts reference them)
  await expectSuccess(client, 'h8', 'file_assets — receipt PDF + 80G cert',
    `INSERT INTO file_assets(id,owner_type,owner_id,original_filename,storage_disk,storage_path,mime_type,file_size_bytes,file_hash_sha256,is_public)
     VALUES ($1,'receipt','placeholder-1','receipt-TEST-001.pdf','s3','receipts/2025/07/TEST-001.pdf','application/pdf',2048,$3,TRUE),
            ($2,'receipt','placeholder-2','80g-TEST-001.pdf','s3','certificates/2025/07/TEST-001.pdf','application/pdf',4096,$4,TRUE)`,
    [ID.file_receipt, ID.file_80g, sha256hex('receipt-test-1'), sha256hex('80g-cert-test-1')]);

  // 2.9 receipts
  await expectSuccess(client, 'h9', 'receipt — generated',
    `INSERT INTO receipts(id,receipt_number,donation_id,payment_id,campaign_id,
       campaign_title_snapshot,donor_name,donor_email,donor_pan,
       amount_minor,currency_code,is_tax_deductible,tax_80g_eligible,
       receipt_file_id,certificate_80g_file_id,state,generated_at,content_hash)
     VALUES ($1,$2,$3,$4,$5,
       'Temple Maintenance 2025','Anita Sharma','anita@example.com','ABCDE1234F',
       100000,'INR',TRUE,TRUE,
       $6,$7,'generated',$8,$9)`,
    [
      ID.receipt_main, 'RCPT-2025-TEST-001',
      ID.donation_main, ID.payment_main, ID.campaign_main,
      ID.file_receipt, ID.file_80g, PAST(0.5),
      sha256hex('test-receipt-content-' + ID.receipt_main),
    ]);

  // 2.10 happy state transitions
  await expectSuccess(client, 'h10', 'donations UPDATE → completed (no-op, already completed)',
    `UPDATE donations SET state='completed', completed_at=COALESCE(completed_at,NOW()) WHERE id=$1`,
    [ID.donation_main]);
  await expectSuccess(client, 'h11', 'payments UPDATE → settled',
    `UPDATE payments SET status='settled', settled_at=NOW() WHERE id=$1`,
    [ID.payment_main]);
  await expectSuccess(client, 'h12', 'receipts UPDATE → delivered',
    `UPDATE receipts SET state='delivered', delivered_at=NOW(), delivery_channel='email' WHERE id=$1`,
    [ID.receipt_main]);

  // 2.11 audit_events INSERT (proves the append-only table is reachable)
  await expectSuccess(client, 'h13', 'audit_events — append-only INSERT',
    `INSERT INTO audit_events(id,actor_type,actor_id,action,entity_type,entity_id,request_id,ip_address,occurred_at,metadata)
     VALUES ($1,'donor',$2,'donation_create','donation',$3,$4,'203.0.113.5'::inet,$5,'{}'::jsonb)`,
    [id('audit'), ID.donor_full, ID.donation_main, 'req_test_001', PAST(2)]);

  // ── 3. Negative probes ────────────────────────────────────────────
  console.log('\nNEGATIVE probes:');

  // n1 — currencies.minor_unit_digits bound
  await expectViolation(client, 'n01', 'currencies.minor_unit_digits > 4 → CHECK (23514)',
    `INSERT INTO currencies(code,name,symbol,minor_unit_digits) VALUES ('XYZ','X','X',9)`,
    '23514');

  // n2 — donor with no PII and not anonymized
  await expectViolation(client, 'n02', 'donor with no contact AND not anonymized → CHECK (23514)',
    `INSERT INTO donors(id) VALUES ($1)`,
    '23514',
    [ID.donor_no_contact]);

  // n3 — donations anonymous-no-PII
  await expectViolation(client, 'n03', 'donations.is_anonymous=true with PII snapshots → CHECK (23514)',
    `INSERT INTO donations(id,campaign_id,amount_minor,currency_code,is_anonymous,donor_name_snapshot,state)
     VALUES ($1,$2,10000,'INR',TRUE,'Should Fail','completed')`,
    '23514',
    [id('don'), ID.campaign_main]);

  // n4 — donations amount_minor
  await expectViolation(client, 'n04', 'donations.amount_minor <= 0 → CHECK (23514)',
    `INSERT INTO donations(id,campaign_id,amount_minor,currency_code,is_anonymous,state) VALUES ($1,$2,0,'INR',FALSE,'completed')`,
    '23514',
    [id('don'), ID.campaign_main]);

  // n5 — C3: donations state='completed' without completed_at
  await expectViolation(client, 'n05', 'donations state=completed with completed_at IS NULL → CHECK (23514)',
    `INSERT INTO donations(id,campaign_id,amount_minor,currency_code,is_anonymous,state,completed_at)
     VALUES ($1,$2,10000,'INR',FALSE,'completed',NULL)`,
    '23514',
    [id('don'), ID.campaign_main]);

  // n6 — C3: donations state='failed' without failed_at
  await expectViolation(client, 'n06', 'donations state=failed with failed_at IS NULL → CHECK (23514)',
    `INSERT INTO donations(id,campaign_id,amount_minor,currency_code,is_anonymous,state,failed_at)
     VALUES ($1,$2,10000,'INR',FALSE,'failed',NULL)`,
    '23514',
    [id('don'), ID.campaign_main]);

  // n7 — C3: payments status='refunded' with amount_refunded_minor=0
  await expectViolation(client, 'n07', 'payments status=refunded with refund=0 → CHECK (23514)',
    `INSERT INTO payments(id,donation_id,provider_code,amount_minor,currency_code,amount_refunded_minor,refunded_at,status)
     VALUES ($1,$2,'razorpay',1000,'INR',0,NOW(),'refunded')`,
    '23514',
    [id('pay'), ID.donation_main]);

  // n8 — payments.amount_refunded_minor > amount_captured_minor
  await expectViolation(client, 'n08', 'payments refund > captured → CHECK (23514)',
    `INSERT INTO payments(id,donation_id,provider_code,amount_minor,currency_code,amount_captured_minor,amount_refunded_minor,status)
     VALUES ($1,$2,'razorpay',1000,'INR',100,500,'captured')`,
    '23514',
    [id('pay'), ID.donation_main]);

  // n9 — C3: payments status='settled' without settled_at
  await expectViolation(client, 'n09', 'payments status=settled with settled_at IS NULL → CHECK (23514)',
    `INSERT INTO payments(id,donation_id,provider_code,amount_minor,currency_code,amount_captured_minor,amount_refunded_minor,status,settled_at)
     VALUES ($1,$2,'razorpay',1000,'INR',1000,0,'settled',NULL)`,
    '23514',
    [id('pay'), ID.donation_main]);

  // n10 — failure_states.retry_count > max_retries
  await expectViolation(client, 'n10', 'failure_states retry_count > max_retries → CHECK (23514)',
    `INSERT INTO failure_states(id,payment_id,classification,failure_code,first_failed_at,last_failed_at,retry_count,max_retries)
     VALUES ($1,$2,'recoverable','GW_TIMEOUT',NOW(),NOW(),5,3)`,
    '23514',
    [ID.failure_state, ID.payment_main]);

  // n11 — idempotency_keys.expires_at <= created_at
  await expectViolation(client, 'n11', 'idempotency_keys expires_at <= created_at → CHECK (23514)',
    `INSERT INTO idempotency_keys(key,scope,request_fingerprint,expires_at)
     VALUES ($1,'donation_create','fp_neg',NOW() - interval '1 hour')`,
    '23514',
    ['ik_neg_' + crypto.randomBytes(8).toString('hex')]);

  // n12 — file_assets.file_size_bytes <= 0
  await expectViolation(client, 'n12', 'file_assets.file_size_bytes <= 0 → CHECK (23514)',
    `INSERT INTO file_assets(id,owner_type,owner_id,original_filename,storage_disk,storage_path,mime_type,file_size_bytes,file_hash_sha256)
     VALUES ($1,'receipt','placeholder','a.pdf','s3','a.pdf','application/pdf',-1,$2)`,
    '23514',
    [id('file'), sha256hex('a')]);

  // n13 — Two static_pages with is_homepage=true
  // Need to first insert a static_page via the hero_banner dependency
  // so is_homepage=true is valid; minimum columns.
  await expectSuccess(client, 'h-prep-n13', 'static_pages — first homepage', `INSERT INTO static_pages(id,slug,title,is_homepage) VALUES ($1,'home','Home',TRUE)`, [ID.staticpage_main]);
  await expectViolation(client, 'n13', 'static_pages second is_homepage=true → EXCLUDE (23P01)',
    `INSERT INTO static_pages(id,slug,title,is_homepage) VALUES ($1,'home-2','Home 2',TRUE)`,
    '23P01',
    [ID.staticpage_other]);

  // n14 — webhook_events duplicate (provider_code, provider_event_id)
  await expectSuccess(client, 'h-prep-n14', 'webhook_events — first event',
    `INSERT INTO webhook_events(id,provider_code,provider_event_id,event_type,payload,headers,signature)
     VALUES ($1,'razorpay','evt_TEST_001','payment.captured','{}'::jsonb,'{}'::jsonb,'sig_001')`,
    [ID.webhook_event_1]);
  await expectViolation(client, 'n14', 'webhook_events duplicate (provider_code, provider_event_id) → UNIQUE (23505)',
    `INSERT INTO webhook_events(id,provider_code,provider_event_id,event_type,payload,headers,signature)
     VALUES ($1,'razorpay','evt_TEST_001','payment.captured','{}'::jsonb,'{}'::jsonb,'sig_002')`,
    '23505',
    [ID.webhook_event_2_dup]);

  // n15 — FK: donations.campaign_id referencing nonexistent campaign
  // Note: state='draft' so the C3 state↔timestamp CHECK doesn't fire first.
  // PG evaluates CHECKs before FKs; if state='completed' we'd see 23514 instead of 23503.
  await expectViolation(client, 'n15', 'donations.campaign_id nonexistent → FK (23503)',
    `INSERT INTO donations(id,campaign_id,amount_minor,currency_code,is_anonymous,state)
     VALUES ($1,$2,10000,'INR',FALSE,'draft')`,
    '23503',
    [id('don'), ID.campaign_orphan]);

  // n16 — FK: receipts.donation_id referencing nonexistent donation
  await expectViolation(client, 'n16', 'receipts.donation_id nonexistent → FK (23503)',
    `INSERT INTO receipts(id,receipt_number,donation_id,payment_id,campaign_id,campaign_title_snapshot,donor_name,amount_minor,currency_code,receipt_file_id,content_hash)
     VALUES ($1,$2,$3,$4,$5,'x','x',1000,'INR',$6,$7)`,
    '23503',
    [id('rec'), 'RCPT-NEG-N16', id('don_nope_a'), id('pay_nope_a'), ID.campaign_main, ID.file_receipt, sha256hex('n16')]);

  // n17 — soft-delete a slug, recreate with same slug → partial unique allows
  await expectBehavior(client, 'n17', 'soft-delete + reuse slug → partial unique allows', 'negative', 'no_error', async () => {
    await client.query(`INSERT INTO static_pages(id,slug,title,is_homepage) VALUES ($1,'reusable-slug','Reusable',FALSE)`, [ID.staticpage_softdeleted]);
    await client.query(`UPDATE static_pages SET deleted_at=NOW() WHERE id=$1`, [ID.staticpage_softdeleted]);
    await client.query(`INSERT INTO static_pages(id,slug,title,is_homepage) VALUES ($1,'reusable-slug','Reusable Reborn',FALSE)`, [ID.staticpage_slugreused]);
    const r = await client.query(`SELECT count(*)::int AS n FROM static_pages WHERE slug='reusable-slug' AND deleted_at IS NULL`);
    if (r.rows[0].n !== 1) throw new Error(`expected 1 live row with slug=reusable-slug, got ${r.rows[0].n}`);
    return {};
  });

  // ── 4. Soft-delete / cascade / restrict probes ────────────────────
  console.log('\nSOFT-DELETE / CASCADE / RESTRICT:');

  // c01 — DELETE a campaign with donations attached → RESTRICT
  // PG fires SQLSTATE 23001 (restrict_violation) on RESTRICT-blocked parent deletes
  // (not 23503, which is reserved for missing-parent inserts).
  await expectViolation(client, 'c01', 'DELETE campaign with donations → RESTRICT (23001)',
    `DELETE FROM campaigns WHERE id=$1`,
    '23001',
    [ID.campaign_main]);

  // c02 — hero_banner CASCADE through hero_banner_pages junction
  await expectBehavior(client, 'c02', 'hero_banner DELETE CASCADES hero_banner_pages → junction rows removed', 'cascade', 'cascade_clean', async () => {
    await client.query(`INSERT INTO static_pages(id,slug,title,is_homepage) VALUES ($1,'page-with-banner','Banner Page',FALSE)`, [ID.staticpage_with_banner]);
    await client.query(`INSERT INTO hero_banners(id,title,state) VALUES ($1,'Test Banner','draft')`, [ID.hero_banner]);
    await client.query(`INSERT INTO hero_banner_pages(hero_banner_id,static_page_id) VALUES ($1,$2)`, [ID.hero_banner, ID.staticpage_with_banner]);
    const before = await client.query(`SELECT count(*)::int AS n FROM hero_banner_pages WHERE hero_banner_id=$1`, [ID.hero_banner]);
    await client.query(`DELETE FROM hero_banners WHERE id=$1`, [ID.hero_banner]);
    const after = await client.query(`SELECT count(*)::int AS n FROM hero_banner_pages WHERE hero_banner_id=$1`, [ID.hero_banner]);
    if (after.rows[0].n !== 0) throw new Error(`expected 0 rows after DELETE, got ${after.rows[0].n}`);
    return { cascade_rows_removed: before.rows[0].n };
  });

  // c03 — SET NULL: delete a file_assets referenced by cover_image_file_id → SET NULL on cover
  await expectBehavior(client, 'c03', 'campaign.cover_image_file_id = SET NULL on file_assets DELETE', 'cascade', 'set_null', async () => {
    const fa = id('file');
    await client.query(`INSERT INTO file_assets(id,owner_type,owner_id,original_filename,storage_disk,storage_path,mime_type,file_size_bytes,file_hash_sha256) VALUES ($1,'campaign_cover',$2,'cover.jpg','s3','cover.jpg','image/jpeg',1024,$3)`, [fa, ID.campaign_main, sha256hex('cover-' + fa)]);
    await client.query(`UPDATE campaigns SET cover_image_file_id=$1 WHERE id=$2`, [fa, ID.campaign_main]);
    await client.query(`DELETE FROM file_assets WHERE id=$1`, [fa]);
    const r = await client.query(`SELECT cover_image_file_id FROM campaigns WHERE id=$1`, [ID.campaign_main]);
    if (r.rows[0].cover_image_file_id !== null) throw new Error(`expected NULL, got ${r.rows[0].cover_image_file_id}`);
    return {};
  });

  // ── 5. Report ────────────────────────────────────────────────────
  const summary = {
    branch,
    ran_at: NOW_ISO(),
    total: probes.length,
    passed: probes.filter((p) => p.status === 'pass').length,
    failed: probes.filter((p) => p.status === 'fail').length,
    probes,
  };

  console.log('\n---');
  console.log(`Result: ${summary.passed}/${summary.total} ${summary.failed === 0 ? 'PASS' : 'with FAILURES'} — schema behaves to spec.`);

  const reportPath = path.resolve(process.cwd(), 'phase-0.5-validation-report.json');
  fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));
  console.log(`Report: ${reportPath}`);

  await client.end();
  process.exit(summary.failed === 0 ? 0 : 1);
} catch (e) {
  console.error('FATAL:', e.message);
  try { await client.end(); } catch {}
  process.exit(2);
}

<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sql = \Illuminate\Support\Facades\DB::connection('neon');

echo '=== BEFORE ===' . PHP_EOL;
$before = $sql->selectOne(
    "SELECT legal_page_content FROM static_pages WHERE slug = 'legal' AND deleted_at IS NULL"
);
echo json_encode($before->legal_page_content, JSON_PRETTY_PRINT) . PHP_EOL;

$newIntro = json_encode([
    'eyebrow' => 'Regulatory standing',
    'title' => "The trust's legal and tax-exempt status",
    'body' => "The trust's public standing rests on four registrations issued by Indian statutory authorities. The scanned certificates are linked below for donor and auditor review. Certified copies are available from the trust office on written request.",
]);

$newCerts = json_encode([
    [
        'certificate_key' => 'eighty_g',
        'title' => '80G Certificate',
        'reference_number' => 'F.No.S-504/80G/CIT/MYS/2011-12',
        'description' => 'Enables donors to claim a tax deduction for contributions to the trust under Section 80G(5)(vi) of the Income Tax Act, 1961. Donations qualify for a 50% deduction subject to the donor’s applicable limits.',
        'icon_key' => 'eighty_g',
        'validity_period' => 'A.Y. 2011-12 onwards',
        'issued_on' => '23.02.2012',
        'issuing_authority' => 'Office of the Commissioner of Income-tax, Mysore',
    ],
    [
        'certificate_key' => 'twelve_a',
        'title' => '12A Registration',
        'reference_number' => 'F.No.S-504/12AA/CIT/MYS/2010-11',
        'description' => "Confirms the trust's registration as a Public Charitable Trust under Section 12A read with Section 12AA(1)(b)(i) of the Income Tax Act, 1961. Tax-exemption availability on the trust's income is considered separately by the Assessing Officer under sections 11 to 13.",
        'icon_key' => 'twelve_a',
        'validity_period' => 'w.e.f. A.Y. 2011-12',
        'issued_on' => '29.10.2010',
        'issuing_authority' => 'Office of the Commissioner of Income-tax, Mysore',
    ],
    [
        'certificate_key' => 'poa',
        'title' => 'Power of Attorney',
        'reference_number' => 'Board Resolution dated 17.07.2021',
        'description' => "Board Resolution of the trust authorising Sh. R. Sriram, Managing Trustee, to execute powers of attorney, open bank accounts, engage professional advisors, sign contracts, and take all steps necessary for the fulfilment of the trust's purposes.",
        'icon_key' => 'poa',
        'validity_period' => 'Continuing (no expiry)',
        'issued_on' => '17.07.2021',
        'issuing_authority' => 'Board of Trustees, VSRSMS',
    ],
    [
        'certificate_key' => 'tan',
        'title' => 'TAN',
        'reference_number' => 'BLRS60956A',
        'description' => 'Tax Deduction Account Number allotted to the trust for withholding-tax compliance under the Income Tax Act, 1961. Mandatory on all TDS challans, certificates, returns, and Tax Collection at Source (TCS) returns filed by the trust.',
        'icon_key' => 'tan',
        'validity_period' => 'Continuing (no expiry)',
        'issued_on' => '01.03.2019',
        'issuing_authority' => 'Income Tax Department (via NSDL e-TDS Intermediary)',
    ],
]);

echo PHP_EOL . '=== UPDATE ===' . PHP_EOL;
$rows = $sql->update(
    "UPDATE static_pages
     SET legal_page_content = jsonb_build_object(
         'version', 1::int,
         'intro', ?::jsonb,
         'certificates', ?::jsonb
     ),
     updated_at = NOW(),
     updated_by = 'system:agent-pass-3'
     WHERE slug = 'legal' AND deleted_at IS NULL",
    [$newIntro, $newCerts]
);
echo 'ROWS_AFFECTED: ' . $rows . PHP_EOL;

echo PHP_EOL . '=== AFTER ===' . PHP_EOL;
$after = $sql->selectOne(
    "SELECT
        legal_page_content,
        updated_at::text,
        updated_by
     FROM static_pages WHERE slug = 'legal' AND deleted_at IS NULL"
);
echo 'updated_at: ' . $after->updated_at . PHP_EOL;
echo 'updated_by: ' . $after->updated_by . PHP_EOL;
echo 'payload:' . PHP_EOL;
echo json_encode($after->legal_page_content, JSON_PRETTY_PRINT) . PHP_EOL;

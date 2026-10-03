<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use DateTimeImmutable;

/**
 * Exports quarterly Form 10BD data as CSV per Income Tax Department rule 114GHA.
 *
 * Form 10BD is the quarterly statement of donations eligible for 80G deduction.
 * It is filed electronically via the ITD portal as a CSV upload.
 *
 * Columns (per ITD specification, + donee TAN required for TDS
 * cross-reference on the filing):
 *   1.  Serial Number
 *   2.  Name of the donee (Trust)
 *   3.  Address of the donee
 *   4.  PAN of the donee
 *   5.  TAN of the donee
 *   6.  Registration Number (80G)
 *   7.  Name of the donor
 *   8.  Address of the donor
 *   9.  PAN of the donor
 *   10. Mode of payment (Cash/Cheque/Bank Draft/Others)
 *   11. Amount of donation (₹)
 *   12. Date of donation (DD/MM/YYYY)
 *   13. Amount in words
 *
 * Donee credentials (name/address/PAN/TAN/80G) MUST be supplied from
 * the DB plane (`trust_identities`, key `canonical`) by the caller —
 * never from env/config. There are no TRUST_* env keys by design.
 *
 * Only receipts with tax80gEligible=true and above the minimum threshold
 * are included.
 */
final class Form10BDExporter
{
    private const CSV_HEADERS = [
        'Sl.No',
        'Name of Donee',
        'Address of Donee',
        'PAN of Donee',
        'TAN of Donee',
        'Registration No (80G)',
        'Name of Donor',
        'Address of Donor',
        'Donor PAN',
        'Mode of Payment',
        'Amount',
        'Date of Donation',
        'Amount in Words',
    ];

    public function __construct(
        private readonly ReceiptRepositoryContract $receipts,
        private readonly DonationRepositoryContract $donations,
        private readonly Clock $clock,
        private readonly ConfigurationContract $config,
    ) {}

    /**
     * Export Form 10BD CSV for a given quarter.
     *
     * @param  DateTimeImmutable  $quarterStart  e.g. 2026-04-01
     * @param  DateTimeImmutable  $quarterEnd    e.g. 2026-06-30
     * @param  string             $trustName
     * @param  string             $trustAddress
     * @param  string             $trustPan
     * @param  string             $trustTan
     * @param  string             $trust80gRegNumber
     *
     * @return Result<string> CSV bytes
     */
    public function export(
        DateTimeImmutable $quarterStart,
        DateTimeImmutable $quarterEnd,
        string $trustName,
        string $trustAddress,
        string $trustPan,
        string $trustTan,
        string $trust80gRegNumber,
    ): Result {
        try {
            // Find all receipts issued in this quarter with 80G eligibility
            $eligibleReceipts = $this->collectEligibleReceipts($quarterStart, $quarterEnd);

            if (empty($eligibleReceipts)) {
                return Result::success($this->buildCsv([]));
            }

            $rows = [];
            $slNo = 1;

            foreach ($eligibleReceipts as [$receipt, $donation]) {
                $rows[] = $this->buildRow(
                    $slNo++,
                    $receipt,
                    $donation,
                    $trustName,
                    $trustAddress,
                    $trustPan,
                    $trustTan,
                    $trust80gRegNumber,
                );
            }

            return Result::success($this->buildCsv($rows));
        } catch (\Throwable $e) {
            return Result::failure("Form10BDExporter: export failed — {$e->getMessage()}");
        }
    }

    /**
     * Pull all ITD-10BD-eligible receipts in [start, end] and pair them with
     * their Donation rows. Eligibility = tax-80G-eligible AND amount ≥ the
     * configured minimum threshold. Returns [] if no rows match.
     *
     * @return array<int, array{0: Receipt, 1: Donation|null}>
     */
    private function collectEligibleReceipts(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): array {
        $minAmountMinor = $this->config->integer(
            'receipts.form_10bd.min_amount_minor',
            50_00,
        );

        $receipts = $this->receipts->findByDateRange($start, $end);

        $pairs = [];
        foreach ($receipts as $receipt) {
            if (! $receipt->tax80gEligible()) {
                continue;
            }
            if ($receipt->amountMinor() < $minAmountMinor) {
                continue;
            }

            $donation = $this->donations->findById($receipt->donationId());
            $pairs[] = [$receipt, $donation];
        }

        return $pairs;
    }

    /**
     * @return array<string, string|int>
     */
    private function buildRow(
        int $slNo,
        Receipt $receipt,
        ?Donation $donation,
        string $trustName,
        string $trustAddress,
        string $trustPan,
        string $trustTan,
        string $trust80gRegNumber,
    ): array {
        $amountMajor = $receipt->amountMinor() / 100;
        $amountWords = $receipt->amountInWords()
            ?? AmountInWords::convert($receipt->amountMinor());

        $mode = (string) ($receipt->metadata()['payment_method'] ?? 'Others');

        return [
            (string) $slNo,
            $trustName,
            $trustAddress,
            $trustPan,
            $trustTan,
            $trust80gRegNumber,
            $receipt->donorName(),
            $this->formatDonorAddress($donation?->donorAddressSnapshot()),
            $receipt->donorPan() ?? '',
            $mode,
            number_format($amountMajor, 2, '.', ''),
            $receipt->generatedAt()->format('d/m/Y'),
            $amountWords,
        ];
    }

    /**
     * @param  array<string, string>|null  $address
     */
    private function formatDonorAddress(?array $address): string
    {
        if ($address === null) {
            return '';
        }

        $parts = [];
        foreach (['line1', 'line2', 'city', 'state', 'pincode'] as $key) {
            if (isset($address[$key]) && $address[$key] !== '') {
                $parts[] = $address[$key];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<array<string, string|int>>  $rows
     */
    private function buildCsv(array $rows): string
    {
        $fp = fopen('php://temp', 'r+');

        fputcsv($fp, self::CSV_HEADERS);

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }
}

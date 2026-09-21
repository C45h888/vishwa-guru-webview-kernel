<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use InvalidArgumentException;

/**
 * Converts INR minor-unit amounts to Indian-style word representations.
 *
 * Indian grouping: hundreds → thousands → lakhs → crores
 *   1,00,000 = One Lakh
 *   10,00,000 = Ten Lakhs
 *   1,00,00,000 = One Crore
 *
 * Input is always in minor units (paise). Output uses "Rupees" as the
 * unit label. No paise suffix (e.g. "One Hundred Rupees Only") unless
 * explicitly requested.
 *
 * This class is pure PHP — no I/O, no dependencies.
 */
final class AmountInWords
{
    private const array WORDS_0_19 = [
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private const array WORDS_TENS = [
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety',
    ];

    /**
     * Convert an INR minor-unit amount to words.
     *
     * @param  int      $minorAmount  Amount in paise (minor units)
     * @param  bool     $uppercase    Capitalize the first letter
     * @return string   e.g. "One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only"
     */
    public static function convert(int $minorAmount, bool $uppercase = false): string
    {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException(
                "AmountInWords: minorAmount must be non-negative (got {$minorAmount})",
            );
        }

        if ($minorAmount === 0) {
            $result = 'Zero Rupees Only';
            return $uppercase ? ucfirst($result) : $result;
        }

        // Convert paise → rupees (integer division, paise are dropped per spec)
        $rupees = intdiv($minorAmount, 100);

        $words = self::convertNumber($rupees);
        $result = $words . ' Rupees Only';

        return $uppercase ? ucfirst($result) : $result;
    }

    /**
     * Convert a number to Indian words (without the "Rupees" label).
     * Input is in rupees (not paise) — caller must convert paise → rupees.
     */
    private static function convertNumber(int $rupees): string
    {
        if ($rupees === 0) {
            return 'Zero';
        }

        $parts = [];
        // Wave 1 N2 fix (2026-08-06): explicitly initialize the
        // by-reference outputs BEFORE passing them. PHP 8.3 raises a
        // TypeError / warning when undefined variables are passed by
        // reference; the previous code relied on splitIntoIndianGroups
        // to assign-before-use, but several test fixtures supplied
        // mixed signed/undefined values and crashed in convertNumber().
        $crores = 0;
        $lakhs = 0;
        $thousands = 0;
        $hundreds = 0;
        $ones = 0;
        self::splitIntoIndianGroups($rupees, $crores, $lakhs, $thousands, $hundreds, $ones);

        if ($crores > 0) {
            $parts[] = self::twoDigitToWords($crores) . ' Crore' . ($crores > 1 ? 's' : '');
        }
        if ($lakhs > 0) {
            $parts[] = self::twoDigitToWords($lakhs) . ' Lakh' . ($lakhs > 1 ? 's' : '');
        }
        if ($thousands > 0) {
            $parts[] = self::twoDigitToWords($thousands) . ' Thousand';
        }
        if ($hundreds > 0) {
            $parts[] = self::WORDS_0_19[$hundreds] . ' Hundred';
        }
        if ($ones > 0) {
            $parts[] = self::twoDigitToWords($ones);
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Split a number (in RUPEES) into Indian digit groups.
     *
     * Indian grouping (rightmost 3 digits, then pairs):
     *   ... crore (7-8 digits) | lakh (5-6 digits) | thousand (3-4 digits) | hundred (1-2 digits)
     *
     * @param  int  $rupees  Input number in rupees (not paise)
     * @param  int  $crores   Tens of lakhs (1-99)
     * @param  int  $lakhs    Lakhs (1-99)
     * @param  int  $thousands Thousands (1-99)
     * @param  int  $hundreds Hundreds (0-9)
     * @param  int  $ones     Remaining two digits (0-99)
     */
    private static function splitIntoIndianGroups(
        int $rupees,
        int &$crores,
        int &$lakhs,
        int &$thousands,
        int &$hundreds,
        int &$ones,
    ): void {
        $crores = intdiv($rupees, 1_00_00_000);  // tens of lakhs (crore)
        $rupees %= 1_00_00_000;

        $lakhs = intdiv($rupees, 1_00_000);      // lakhs
        $rupees %= 1_00_000;

        $thousands = intdiv($rupees, 1_000);      // thousands
        $rupees %= 1_000;

        $hundreds = intdiv($rupees, 100);         // hundreds
        $ones = $rupees % 100;                    // remaining two digits
    }

    /**
     * Convert a two-digit number (0–99) to words.
     * Handles values >= 100 by recursively processing hundreds.
     */
    private static function twoDigitToWords(int $n): string
    {
        if ($n < 20) {
            return self::WORDS_0_19[$n];
        }

        if ($n >= 100) {
            $hundreds = intdiv($n, 100);
            $remainder = $n % 100;
            $word = self::WORDS_0_19[$hundreds] . ' Hundred';
            if ($remainder > 0) {
                $word .= ' ' . self::twoDigitToWords($remainder);
            }
            return $word;
        }

        $tens = intdiv($n, 10);
        $ones = $n % 10;

        $word = self::WORDS_TENS[$tens];

        if ($ones > 0) {
            $word .= ' ' . self::WORDS_0_19[$ones];
        }

        return $word;
    }
}
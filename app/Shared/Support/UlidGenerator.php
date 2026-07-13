<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * ULID generator.
 * ULIDs are lexicographically sortable, URL-safe identifiers.
 * Format: 01ARZ3NDEKTSV4RRFFQ69G5FAV (26 characters, Crockford Base32).
 */
class UlidGenerator implements IdentifierGenerator
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const ENCODING_LEN = 32;
    private const TIME_LEN = 10;
    private const RANDOM_LEN = 16;

    /**
     * Generate a new ULID string.
     *
     * @return string 26-character ULID
     */
    public static function generate(): string
    {
        $time = self::timeComponent();
        $random = self::randomComponent();

        return $time . $random;
    }

    /**
     * IdentifierGenerator::next() implementation.
     * Returns a fresh ULID string.
     */
    public function next(): string
    {
        return self::generate();
    }

    /**
     * Generate from a specific Unix timestamp (seconds).
     *
     * @param int $timestamp
     * @return string
     */
    public static function generateFromTimestamp(int $timestamp): string
    {
        $time = self::encodeTime($timestamp, self::TIME_LEN);
        $random = self::randomComponent();

        return $time . $random;
    }

    /**
     * Extract the Unix timestamp from a ULID.
     *
     * @param string $ulid
     * @return int
     */
    public static function timestamp(string $ulid): int
    {
        $ulid = strtoupper($ulid);

        $timeChars = substr($ulid, 0, self::TIME_LEN);
        $decoded = 0;

        for ($i = 0; $i < self::TIME_LEN; $i++) {
            $char = $timeChars[$i];
            $pos = strpos(self::ENCODING, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException("Invalid ULID character: {$char}");
            }
            $decoded = ($decoded * self::ENCODING_LEN) + $pos;
        }

        return $decoded;
    }

    /**
     * Validate a ULID format (not uniqueness).
     *
     * @param string $ulid
     * @return bool
     */
    public static function isValid(string $ulid): bool
    {
        if (strlen($ulid) !== 26) {
            return false;
        }

        $ulid = strtoupper($ulid);
        for ($i = 0; $i < 26; $i++) {
            if (strpos(self::ENCODING, $ulid[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    private static function timeComponent(): string
    {
        return self::encodeTime(time(), self::TIME_LEN);
    }

    private static function encodeTime(int $time, int $len): string
    {
        $result = '';
        for ($i = $len - 1; $i >= 0; $i--) {
            $mod = $time % self::ENCODING_LEN;
            $result = self::ENCODING[$mod] . $result;
            $time = intdiv($time, self::ENCODING_LEN);
        }
        return $result;
    }

    private static function randomComponent(): string
    {
        $result = '';
        for ($i = 0; $i < self::RANDOM_LEN; $i++) {
            $result .= self::ENCODING[random_int(0, self::ENCODING_LEN - 1)];
        }
        return $result;
    }
}

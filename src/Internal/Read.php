<?php

declare(strict_types=1);

namespace GoSuccess\WordpressOrg\Internal;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns foreign field values into typed values without ever throwing: anything
 * that does not fit becomes `null` or an empty collection.
 *
 * @internal
 */
final class Read
{
    /** `2021-01-30` */
    public const string DATE = 'Y-m-d';

    /** `2026-11-03 19:00:00` */
    public const string DATETIME = 'Y-m-d H:i:s';

    /** `2026-08-18 11:42pm GMT`, as used by `last_updated`. */
    public const string UPDATED = 'Y-m-d g:ia \G\M\T';

    private function __construct() {}

    public static function string(mixed $value): ?string
    {
        if (\is_string($value)) {
            return $value;
        }

        return \is_int($value) || \is_float($value) ? (string) $value : null;
    }

    public static function int(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (!\is_string($value) || preg_match('/^-?\d+$/D', $value) !== 1) {
            return null;
        }

        // Digits beyond the integer range would silently clamp.
        return abs((float) $value) < \PHP_INT_MAX ? (int) $value : null;
    }

    public static function float(mixed $value): ?float
    {
        if (\is_int($value)) {
            return (float) $value;
        }

        if (\is_string($value) && is_numeric($value)) {
            $value = (float) $value;
        }

        return \is_float($value) && is_finite($value) ? $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => null,
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        return array_values(array_filter(self::array($value), \is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    public static function stringMap(mixed $value): array
    {
        $map = [];

        foreach (self::array($value) as $key => $item) {
            if (\is_string($item)) {
                $map[(string) $key] = $item;
            }
        }

        return $map;
    }

    /**
     * Star count => number of ratings. PHP turns the keys "1".."5" into integers;
     * entries with other keys or non-integer counts are skipped.
     *
     * @return array<int, int>
     */
    public static function ratings(mixed $value): array
    {
        $ratings = [];

        foreach (self::array($value) as $stars => $count) {
            if (\is_int($stars) && \is_int($count)) {
                $ratings[$stars] = $count;
            }
        }

        return $ratings;
    }

    /**
     * Parse a UTC date; the formats are tried in order. A value is accepted only
     * if formatting it back reproduces the input, so `2026-02-30` is `null`.
     */
    public static function date(mixed $value, string ...$formats): ?DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        $utc = new DateTimeZone('UTC');

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value, $utc);

            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}

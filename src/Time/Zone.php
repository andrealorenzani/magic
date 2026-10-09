<?php
declare(strict_types=1);

namespace Magic\Time;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class Zone
{
    public static function isValid(string $timeZone): bool
    {
        return in_array($timeZone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Unix timestamp (UTC) for a wall-clock time at the birthplace.
     * Uses PHP's tz database, so historical offsets and DST apply.
     * DST gap -> shifted forward; overlap -> first occurrence.
     */
    public static function toUnix(int $year, int $month, int $day, int $hour, int $minute, string $timeZone): int
    {
        if (!self::isValid($timeZone)) {
            throw new InvalidArgumentException('Unknown time zone');
        }
        if (!checkdate($month, $day, $year) || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidArgumentException('Invalid date or time');
        }
        $local = sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
        return (new DateTimeImmutable($local, new DateTimeZone($timeZone)))->getTimestamp();
    }

    /** Calendar day (Y-m-d) at a time zone for a Unix timestamp. */
    public static function dateAt(int $unix, string $timeZone): string
    {
        return self::at($unix, $timeZone)->format('Y-m-d');
    }

    /** Offset from UTC in minutes (east positive) at a time zone for a Unix timestamp. */
    public static function offsetMinutes(int $unix, string $timeZone): int
    {
        return intdiv(self::at($unix, $timeZone)->getOffset(), 60);
    }

    private static function at(int $unix, string $timeZone): DateTimeImmutable
    {
        if (!self::isValid($timeZone)) {
            throw new InvalidArgumentException('Unknown time zone');
        }
        return (new DateTimeImmutable('@' . $unix))->setTimezone(new DateTimeZone($timeZone));
    }
}

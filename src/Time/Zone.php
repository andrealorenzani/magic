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
}

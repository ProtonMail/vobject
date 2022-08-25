<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

use DateTimeZone;
use Sabre\VObject\TimeZoneUtil;

/**
 * Try to ignore the trailing number of the Microsoft timezone.
 *
 * For example: Eastern Standard Time 1 => Eastern Standard Time
 */
class FindFromMzVersionTimezone implements TimezoneFinder
{
    public function find(string $tzid, ?bool $failIfUncertain = false): ?DateTimeZone
    {
        if (strlen($tzid) < 1) {
            return null;
        }

        $trailingChar = (int) $tzid[strlen($tzid)-1];
        if ($trailingChar <= 9 && $trailingChar >= 1) {
            $emptySpace = strrpos($tzid, ' ');
            if ($emptySpace === false) {
                return null;
            }

            $tz = TimeZoneUtil::getTimeZone(substr($tzid, 0, $emptySpace));
            if ($tz->getName() === 'UTC') {
                return null;
            }

            return $tz;
        }

        return null;
    }
}

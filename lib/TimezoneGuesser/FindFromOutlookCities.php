<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

use DateTimeZone;

class FindFromOutlookCities implements TimezoneFinder
{
    /**
     * Example: TZID:(UTC+01:00) Bruxelles\, København\, Madrid\, Paris
     */
    public function find(string $tzid, ?bool $failIfUncertain = false): ?DateTimeZone
    {
        $tzid = preg_replace('/TZID:\(UTC(\+|\-)\d{2}:\d{2}\)/', '', $tzid, -1, $count);
        if ($count === 0) {
            return null;
        }

        $tzid = trim($tzid);

        // Remove backslash
        $tzid = str_replace('\\', '', $tzid);

        $cities = explode(', ', $tzid);

        if (count($cities) === 1) {
            return null;
        }

        $tzIdentifiers = DateTimeZone::listIdentifiers();

        foreach ($cities as $city) {
            foreach ($tzIdentifiers as $tzIdentifier) {
                if (str_contains(strtolower($tzIdentifier), strtolower($city))) {
                    return new DateTimeZone($tzIdentifier);
                }
            }
        }

        return null;
    }
}

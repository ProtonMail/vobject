<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

use Sabre\VObject\Component\VTimeZone;
use Sabre\VObject\Recur\RRuleIterator;
use Sabre\VObject\TimeZoneUtil;

class GuessFromCustomizedTimeZone implements TimezoneGuesser
{
    public function guess(VTimeZone $vtimezone, ?bool $failIfUncertain = false): ?\DateTimeZone
    {
        if (null === $vtimezone->TZID || 'Customized Time Zone' !== $vtimezone->TZID->getValue()) {
            return null;
        }

        $timezones = \DateTimeZone::listIdentifiers();
        $standard = $vtimezone->STANDARD;
        $daylight = $vtimezone->DAYLIGHT;
        if (!$standard) {
            return null;
        }

        $standardOffset = $standard->TZOFFSETTO;
        if (!$standardOffset) {
            return null;
        }
        $standardOffset = $standardOffset->getValue();

        $standardRRule = $standard->RRULE ? $standard->RRULE->getValue() : 'FREQ=DAILY';
        // The guess will not be perfectly matched since we use the timezone data of the current year
        // It might be wrong if the timezone data changed in the past
        $year = (new \DateTimeImmutable('now'))->format('Y');
        $start = new \DateTimeImmutable($year.'-01-01');
        $standardIterator = new RRuleIterator($standardRRule, $start);
        $standardIterator->next();

        if ($daylight && !$daylight->TZOFFSETTO) {
            $daylight = null;
        }
        $daylightOffset = $daylight ? $daylight->TZOFFSETTO->getValue() : '';
        $daylightRRule = $daylight ? ($daylight->RRULE ? $daylight->RRULE->getValue() : 'FREQ=DAILY') : '';
        $daylightIterator = $daylight ? new RRuleIterator($daylightRRule, $standardIterator->current()) : null;
        $daylightIterator && $daylightIterator->next();

        $day = 24 * 60 * 60;
        foreach ($timezones as $timezone) {
            $tz = new \DateTimeZone($timezone);
            // check standard
            $timestamp = $standardIterator->current()->getTimestamp();
            $transitions = $tz->getTransitions($timestamp + $day, $timestamp + $day + 1);
            if (empty($transitions)) {
                continue;
            }

            $checkOffset = $transitions[0]['offset'];

            if ($checkOffset !== $this->parseOffsetToInteger($standardOffset)) {
                continue;
            }

            if (!$daylight) {
                return TimeZoneUtil::getTimeZone($timezone, null, $failIfUncertain);
            }

            // check daylight
            $timestamp = $daylightIterator->current()->getTimestamp();
            $transitions = $tz->getTransitions($timestamp + $day, $timestamp + $day + 1);
            if (empty($transitions)) {
                continue;
            }

            $checkOffset = $transitions[0]['offset'];
            if ($checkOffset === $this->parseOffsetToInteger($daylightOffset)) {
                return TimeZoneUtil::getTimeZone($timezone, null, $failIfUncertain);
            }
        }

        return null;
    }

    private function parseOffsetToInteger(string $offset): int
    {
        $time = ((int) ($offset[1].$offset[2]) * 60) + (int) ($offset[3].$offset[4]);

        $time = $time * 60;

        if ('-' === $offset[0]) {
            $time = $time * -1;
        }

        return $time;
    }
}

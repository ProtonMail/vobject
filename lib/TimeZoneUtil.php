<?php

namespace Sabre\VObject;

use Sabre\VObject\TimezoneGuesser\FindFromMzVersionTimezone;
use Sabre\VObject\TimezoneGuesser\FindFromOffset;
use Sabre\VObject\TimezoneGuesser\FindFromOffsetName;
use Sabre\VObject\TimezoneGuesser\FindFromOutlookCities;
use Sabre\VObject\TimezoneGuesser\FindFromTimezoneIdentifier;
use Sabre\VObject\TimezoneGuesser\FindFromTimezoneMap;
use Sabre\VObject\TimezoneGuesser\GuessFromCustomizedTimeZone;
use Sabre\VObject\TimezoneGuesser\GuessFromLicEntry;
use Sabre\VObject\TimezoneGuesser\GuessFromMsTzId;
use Sabre\VObject\TimezoneGuesser\LowercaseTimezoneIdentifier;
use Sabre\VObject\TimezoneGuesser\TimezoneFinder;
use Sabre\VObject\TimezoneGuesser\TimezoneGuesser;

/**
 * Time zone name translation.
 *
 * This file translates well-known time zone names into "Olson database" time zone names.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Frank Edelhaeuser (fedel@users.sourceforge.net)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class TimeZoneUtil
{
    private static ?TimeZoneUtil $instance = null;

    /** @var TimezoneGuesser[] */
    private array $timezoneGuessers = [];

    /** @var TimezoneFinder[] */
    private array $timezoneFinders = [];

    private function __construct()
    {
        $this->addGuesser('lic', new GuessFromLicEntry());
        $this->addGuesser('msTzId', new GuessFromMsTzId());
        $this->addFinder('tzid', new FindFromTimezoneIdentifier());
        $this->addFinder('tzmap', new FindFromTimezoneMap());
        $this->addFinder('offset', new FindFromOffset());
        $this->addFinder('lowercase', new LowercaseTimezoneIdentifier());
        $this->addFinder('outlookCities', new FindFromOutlookCities());
        $this->addFinder('version', new FindFromMzVersionTimezone());
        $this->addFinder('offsetName', new FindFromOffsetName());
    }

    private static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function addGuesser(string $key, TimezoneGuesser $guesser): void
    {
        $this->timezoneGuessers[$key] = $guesser;
    }

    private function addFinder(string $key, TimezoneFinder $finder): void
    {
        $this->timezoneFinders[$key] = $finder;
    }

    /**
     * This method will try to find out the correct timezone for an iCalendar
     * date-time value.
     *
     * You must pass the contents of the TZID parameter, as well as the full
     * calendar.
     *
     * If the lookup fails, this method will return the default PHP timezone
     * (as configured using date_default_timezone_set, or the date.timezone ini
     * setting).
     *
     * Alternatively, if $failIfUncertain is set to true, it will throw an
     * exception if we cannot accurately determine the timezone.
     */
    private function findTimeZone(string $tzid, ?Component $vcalendar = null, bool $failIfUncertain = false, bool $activeCustomizedGuesser = false): \DateTimeZone
    {
        foreach ($this->timezoneFinders as $timezoneFinder) {
            $timezone = $timezoneFinder->find($tzid, $failIfUncertain);
            if (!$timezone instanceof \DateTimeZone) {
                continue;
            }

            return $timezone;
        }

        if (!$activeCustomizedGuesser) {
            unset($this->timezoneGuessers['customized']);
        }

        if ($vcalendar) {
            // We temporary add the customized timezone guesser if needed
            $guessers = $this->timezoneGuessers;
            if ($activeCustomizedGuesser) {
                $guessers[] = new GuessFromCustomizedTimeZone();
            }

            // If that didn't work, we will scan VTIMEZONE objects
            foreach ($vcalendar->select('VTIMEZONE') as $vtimezone) {
                if ((string) $vtimezone->TZID === $tzid) {
                    foreach ($guessers as $timezoneGuesser) {
                        $timezone = $timezoneGuesser->guess($vtimezone, $failIfUncertain);
                        if (!$timezone instanceof \DateTimeZone) {
                            continue;
                        }

                        return $timezone;
                    }
                }
            }
        }

        if ($failIfUncertain) {
            throw new \InvalidArgumentException('We were unable to determine the correct PHP timezone for tzid: '.$tzid);
        }

        // If we got all the way here, we default to whatever has been set as the PHP default timezone.
        return new \DateTimeZone(date_default_timezone_get());
    }

    public static function addTimezoneGuesser(string $key, TimezoneGuesser $guesser): void
    {
        self::getInstance()->addGuesser($key, $guesser);
    }

    public static function addTimezoneFinder(string $key, TimezoneFinder $finder): void
    {
        self::getInstance()->addFinder($key, $finder);
    }

    public static function getTimeZone(string $tzid, ?Component $vcalendar = null, bool $failIfUncertain = false, bool $activeCustomizedGuesser = true): \DateTimeZone
    {
        return self::getInstance()->findTimeZone($tzid, $vcalendar, $failIfUncertain, $activeCustomizedGuesser);
    }

    public static function clean(): void
    {
        self::$instance = null;
    }
}

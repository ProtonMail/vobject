<?php

namespace Sabre\VObject;

use PHPUnit\Framework\TestCase;

class TimeZoneUtilTest extends TestCase
{
    public function setUp(): void
    {
        TimeZoneUtil::clean();
    }

    /**
     * @dataProvider getMapping
     */
    public function testCorrectTZ(string $timezoneName): void
    {
        try {
            $tz = new \DateTimeZone($timezoneName);
            self::assertInstanceOf('DateTimeZone', $tz);
        } catch (\Exception $e) {
            if (false !== strpos($e->getMessage(), 'Unknown or bad timezone')) {
                $this->markTestSkipped($timezoneName.' is not (yet) supported in this PHP version. Update pecl/timezonedb');
            } else {
                throw $e;
            }
        }
    }

    public function getMapping(): array
    {
        $map = array_merge(
            include __DIR__.'/../../lib/timezonedata/windowszones.php',
            include __DIR__.'/../../lib/timezonedata/lotuszones.php',
            include __DIR__.'/../../lib/timezonedata/exchangezones.php',
            include __DIR__.'/../../lib/timezonedata/php-workaround.php',
            include __DIR__.'/../../lib/timezonedata/extrazones.php',
        );

        // PHPUNit requires an array of arrays
        return array_map(
            function ($value) {
                return [$value];
            },
            $map
        );
    }

    /**
     * @dataProvider getMapping
     */
    public function testSlashTZ($timezonename): void
    {
        $slashTimezone = '/'.$timezonename;
        $expected = TimeZoneUtil::getTimeZone($timezonename)->getName();
        $actual = TimeZoneUtil::getTimeZone($slashTimezone)->getName();
        self::assertEquals($expected, $actual);
    }

    public function testExchangeMap(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:foo
X-MICROSOFT-CDO-TZID:2
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20120416T092149Z
DTSTART;TZID="foo":20120418T1
 00000
SUMMARY:Begin Unterhaltsreinigung
UID:040000008200E00074C5B7101A82E0080000000010DA091DC31BCD01000000000000000
 0100000008FECD2E607780649BE5A4C9EE6418CBC
 000
END:VEVENT
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('foo', Reader::read($vobj));
        $ex = new \DateTimeZone('Europe/Lisbon');

        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testWhetherMicrosoftIsStillInsane(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:(GMT+01.00) Sarajevo/Warsaw/Zagreb
X-MICROSOFT-CDO-TZID:2
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('(GMT+01.00) Sarajevo/Warsaw/Zagreb', Reader::read($vobj));
        $ex = new \DateTimeZone('Europe/Sarajevo');

        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testUnknownExchangeId(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:foo
X-MICROSOFT-CDO-TZID:2000
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20120416T092149Z
DTSTART;TZID="foo":20120418T1
 00000
SUMMARY:Begin Unterhaltsreinigung
UID:040000008200E00074C5B7101A82E0080000000010DA091DC31BCD01000000000000000
 0100000008FECD2E607780649BE5A4C9EE6418CBC
DTEND;TZID="Sarajevo, Skopje, Sofija, Vilnius, Warsaw, Zagreb":20120418T103
 000
END:VEVENT
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('foo', Reader::read($vobj));
        $ex = new \DateTimeZone(date_default_timezone_get());
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testEmptyTimeZone(): void
    {
        $tz = TimeZoneUtil::getTimeZone('');
        $ex = new \DateTimeZone('UTC');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testWindowsTimeZone(): void
    {
        $tz = TimeZoneUtil::getTimeZone('Eastern Standard Time');
        $ex = new \DateTimeZone('America/New_York');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testLowerCaseTimeZone(): void
    {
        $tz = TimeZoneUtil::getTimeZone('mountain time (us & canada)');
        $ex = new \DateTimeZone('America/Denver');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testDeprecatedTimeZone(): void
    {
        // Deprecated in 2022b
        $tz = TimeZoneUtil::getTimeZone('Europe/Kiev');
        $ex = new \DateTimeZone('Europe/Kiev');
        self::assertSame($ex->getName(), $tz->getName());
    }

    public function testDeprecatedUnsupportedTimeZone(): void
    {
        // Deprecated and unsupported
        $tz = TimeZoneUtil::getTimeZone('America/Godthab');
        $ex = new \DateTimeZone('America/Godthab');
        self::assertNotSame($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider getPHPTimeZoneIdentifiers
     */
    public function testTimeZoneIdentifiers(string $tzid): void
    {
        $tz = TimeZoneUtil::getTimeZone($tzid);
        $ex = new \DateTimeZone($tzid);

        self::assertEquals($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider getPHPTimeZoneBCIdentifiers
     */
    public function testTimeZoneBCIdentifiers(string $tzid): void
    {
        /*
         * A regression was introduced in PHP 8.1.14 and 8.2.1
         * Timezone ids containing a "+" like "GMT+10" do not work.
         * See https://github.com/php/php-src/issues/10218
         * The regression should be fixed in the next patch releases of PHP
         * that should be released in Feb 2023.
         */
        $versionOfPHP = \phpversion();
        if ((('8.1.14' == $versionOfPHP) || ('8.2.1' == $versionOfPHP)) && \str_contains($tzid, '+')) {
            $this->markTestSkipped("Timezone ids containing '+' do not work on PHP $versionOfPHP");
        }
        $tz = TimeZoneUtil::getTimeZone($tzid);
        $ex = new \DateTimeZone($tzid);

        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function getPHPTimeZoneIdentifiers(): array
    {
        // PHPUNit requires an array of arrays
        return array_map(
            function ($value) {
                return [$value];
            },
            // FIXME remove the filter after finishing timezone migration
            array_filter(\DateTimeZone::listIdentifiers(), static function (string $timezone) {
                return 'Europe/Kyiv' !== $timezone;
            })
        );
    }

    public function getPHPTimeZoneBCIdentifiers(): array
    {
        // PHPUNit requires an array of arrays
        return array_map(
            function ($value) {
                return [$value];
            },
            include __DIR__.'/../../lib/timezonedata/php-bc.php'
        );
    }

    public function testKyivTimezone(): void
    {
        self::assertSame('Europe/Kiev', TimeZoneUtil::getTimeZone('Europe/Kyiv')->getName());
    }

    public function testTimezoneOffset(): void
    {
        $tz = TimeZoneUtil::getTimeZone('GMT-0400', null, true);

        if (version_compare(PHP_VERSION, '5.5.10', '>=') && !defined('HHVM_VERSION')) {
            $ex = new \DateTimeZone('-04:00');
        } else {
            $ex = new \DateTimeZone('Etc/GMT-4');
        }
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testTimezoneFail(): void
    {
        self::expectException(\InvalidArgumentException::class);
        TimeZoneUtil::getTimeZone('FooBar', null, true);
    }

    public function testFallBack(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:foo
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20120416T092149Z
DTSTART;TZID="foo":20120418T1
 00000
SUMMARY:Begin Unterhaltsreinigung
UID:040000008200E00074C5B7101A82E0080000000010DA091DC31BCD01000000000000000
 0100000008FECD2E607780649BE5A4C9EE6418CBC
 000
END:VEVENT
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('foo', Reader::read($vobj));
        $ex = new \DateTimeZone(date_default_timezone_get());
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testLjubljanaBug(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana
X-LIC-LOCATION:Europe/Ljubljana
BEGIN:STANDARD
TZNAME:CET
DTSTART:19701028T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:CEST
DTSTART:19700325T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
UID:foo
DTSTART;TZID=/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana:
 20121003T080000
DTEND;TZID=/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana:
 20121003T083000
TRANSP:OPAQUE
SEQUENCE:2
SUMMARY:testing
CREATED:20121002T172613Z
LAST-MODIFIED:20121002T172613Z
END:VEVENT
END:VCALENDAR

HI;

        $tz = TimeZoneUtil::getTimeZone('/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana', Reader::read($vobj));
        $ex = new \DateTimeZone('Europe/Ljubljana');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testWeirdSystemVLICs(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT
X-LIC-LOCATION:SystemV/EST5EDT
BEGIN:STANDARD
TZNAME:EST
DTSTART:19701104T020000
RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11
TZOFFSETFROM:-0400
TZOFFSETTO:-0500
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:EDT
DTSTART:19700311T020000
RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3
TZOFFSETFROM:-0500
TZOFFSETTO:-0400
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
UID:20121026T021107Z-6301-1000-1-0@chAir
DTSTAMP:20120905T172126Z
DTSTART;TZID=/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT:
 20121026T153000
DTEND;TZID=/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT:
 20121026T160000
TRANSP:OPAQUE
SEQUENCE:5
SUMMARY:pick up Ibby
CLASS:PUBLIC
CREATED:20121026T021108Z
LAST-MODIFIED:20121026T021118Z
X-EVOLUTION-MOVE-CALENDAR:1
END:VEVENT
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT', Reader::read($vobj), true);
        $ex = new \DateTimeZone('America/New_York');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testPrefixedOffsetExchangeIdentifier(): void
    {
        $tz = TimeZoneUtil::getTimeZone('(UTC-05:00) Eastern Time (US & Canada)');
        $ex = new \DateTimeZone('America/New_York');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testMicrosoftMap(): void
    {
        $tz = TimeZoneUtil::getTimeZone('tzone://Microsoft/Utc', null, true);
        $ex = new \DateTimeZone('UTC');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider unSupportTimezoneProvider
     */
    public function testPHPUnSupportTimeZone(string $origin, string $expected): void
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, true);
        $ex = new \DateTimeZone($expected);
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function unSupportTimezoneProvider(): iterable
    {
        yield 'America/Santa_Isabel' => [
            'origin' => 'America/Santa_Isabel',
            'expected' => 'America/Tijuana',
        ];

        yield 'Asia/Chongqing' => [
            'origin' => 'Asia/Chongqing',
            'expected' => 'Asia/Shanghai',
        ];

        yield 'Asia/Harbin' => [
            'origin' => 'Asia/Harbin',
            'expected' => 'Asia/Shanghai',
        ];

        yield 'Asia/Kashgar' => [
            'origin' => 'Asia/Kashgar',
            'expected' => 'Asia/Urumqi',
        ];

        yield 'Pacific/Johnston' => [
            'origin' => 'Pacific/Johnston',
            'expected' => 'Pacific/Honolulu',
        ];

        yield 'EDT' => [
            'origin' => 'EDT',
            'expected' => 'America/Manaus',
        ];

        yield 'CDT' => [
            'origin' => 'CDT',
            'expected' => 'America/Chicago',
        ];

        yield 'PST' => [
            'origin' => 'PST',
            'expected' => 'America/Los_Angeles',
        ];

        yield 'Gulf Standard Time' => [
            'origin' => 'Gulf Standard Time',
            'expected' => 'Asia/Dubai',
        ];

        if (($handle = fopen(__DIR__.'/microsoft-timezones-confluence.csv', 'r')) !== false) {
            $data = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== false) {
                yield $data[0] => [
                    'origin' => $data[0],
                    'expected' => '' !== $data[2] ? $data[2] : $data[1],
                ];
            }
            fclose($handle);
        }
    }

    /**
     * @dataProvider offsetTimeZoneProvider
     */
    public function testOffsetTimeZones(string $origin, string $expected): void
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, true);
        $ex = new \DateTimeZone($expected);
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function offsetTimeZoneProvider(): iterable
    {
        yield 'UTC-05:00' => [
            'origin' => 'UTC-05:00',
            'expected' => 'America/Lima',
        ];

        yield '-5' => [
            'origin' => '-5',
            'expected' => 'America/Lima',
        ];

        yield '-05' => [
            'origin' => '-05',
            'expected' => 'America/Lima',
        ];

        yield '-05:00' => [
            'origin' => '-05:00',
            'expected' => 'America/Lima',
        ];
    }

    /**
     * @dataProvider letterCaseTimeZoneProvider
     */
    public function testDifferentLetterCaseTimeZone(string $origin, string $expected): void
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, true);
        $ex = new \DateTimeZone($expected);
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function letterCaseTimeZoneProvider(): iterable
    {
        yield 'case 1' => [
            'origin' => 'Europe/paris',
            'expected' => 'Europe/Paris',
        ];

        yield 'case 2' => [
            'origin' => 'europe/paris',
            'expected' => 'Europe/Paris',
        ];

        yield 'case 3' => [
            'origin' => 'Europe/pAris',
            'expected' => 'Europe/Paris',
        ];

        yield 'case 4' => [
            'origin' => 'Asia/taipei',
            'expected' => 'Asia/Taipei',
        ];
    }

    /**
     * @dataProvider outlookCitiesProvider
     */
    public function testOutlookCities(string $origin, bool $failIfUncertain, string $expected): void
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, $failIfUncertain);
        $ex = new \DateTimeZone($expected);
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function outlookCitiesProvider(): iterable
    {
        yield 'case 1' => [
            'origin' => 'TZID:(UTC+01:00) Bruxelles\, København\, Madrid\, Paris',
            'failIfUncertain' => true,
            'expected' => 'Europe/Madrid',
        ];

        yield 'case 2' => [
            'origin' => 'TZID:(UTC+01:00) Bruxelles, København, Madrid, Paris',
            'failIfUncertain' => true,
            'expected' => 'Europe/Madrid',
        ];

        yield 'case 3' => [
            'origin' => 'TZID:(UTC+01:00)Bruxelles\, København\, Madrid\, Paris',
            'failIfUncertain' => true,
            'expected' => 'Europe/Madrid',
        ];

        yield 'case 4' => [
            'origin' => 'Bruxelles\, København\, Madrid\, Paris',
            'failIfUncertain' => false,
            'expected' => 'UTC',
        ];
    }

    /**
     * @dataProvider versionTzProvider
     */
    public function testVersionTz(string $origin, bool $failIfUncertain, string $expected): void
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, $failIfUncertain);
        $ex = new \DateTimeZone($expected);
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function versionTzProvider(): iterable
    {
        yield 'case 1' => [
            'origin' => 'Eastern Standard Time 1',
            'failIfUncertain' => true,
            'expected' => 'America/New_York',
        ];

        yield 'case 2' => [
            'origin' => 'Eastern Standard Time 2',
            'failIfUncertain' => true,
            'expected' => 'America/New_York',
        ];
    }

    public function testCustomizedTimeZone(): void
    {
        $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//ical.marudot.com//iCal Event Maker 
X-WR-CALNAME:Victorian public holiday dates
NAME:Victorian public holiday dates
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Customized Time Zone
TZURL:http://tzurl.org/zoneinfo-outlook/Australia/Sydney
X-LIC-LOCATION:Customized Time Zone
BEGIN:STANDARD
TZOFFSETFROM:+1100
TZOFFSETTO:+1000
TZNAME:AEST
DTSTART:19700405T030000
RRULE:FREQ=YEARLY;BYMONTH=4;BYDAY=1SU
END:STANDARD
BEGIN:DAYLIGHT
TZOFFSETFROM:+1000
TZOFFSETTO:+1100
TZNAME:AEDT
DTSTART:19701004T020000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20200907T032724Z
UID:problematicTimezone@example.com
DTSTART;TZID=Customized Time Zone:20210611T110000
DTEND;TZID=Customized Time Zone:20210611T113000
SUMMARY:customized time zone
END:VEVENT
END:VCALENDAR
ICS;

        $tz = TimeZoneUtil::getTimeZone('Customized Time Zone', Reader::read($ics));
        self::assertNotSame('Customized Time Zone', $tz->getName());
        $start = new \DateTimeImmutable('2022-04-25');
        self::assertSame(10 * 60 * 60, $tz->getOffset($start));

        $start = new \DateTimeImmutable('2022-11-10');
        self::assertSame(11 * 60 * 60, $tz->getOffset($start));
    }

    public function testCustomizedTimeZone2(): void
    {
        $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//ical.marudot.com//iCal Event Maker 
X-WR-CALNAME:test
NAME:test
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Customized Time Zone
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20200907T032724Z
UID:problematicTimezone@example.com
DTSTART;TZID=Customized Time Zone:20210611T110000
DTEND;TZID=Customized Time Zone:20210611T113000
SUMMARY:customized time zone
END:VEVENT
END:VCALENDAR
ICS;

        $tz = TimeZoneUtil::getTimeZone('Customized Time Zone', Reader::read($ics));
        self::assertNotSame('Customized Time Zone', $tz->getName());
        $start = new \DateTimeImmutable('2022-04-25');
        self::assertSame(2 * 60 * 60, $tz->getOffset($start));

        $start = new \DateTimeImmutable('2022-11-10');
        self::assertSame(60 * 60, $tz->getOffset($start));
    }

    public function testCustomizedTimeZoneWithoutDaylight(): void
    {
        $ics = $this->getCustomizedICS();
        $tz = TimeZoneUtil::getTimeZone('Customized Time Zone', Reader::read($ics));
        self::assertSame('Antarctica/Casey', $tz->getName());
        $start = new \DateTimeImmutable('2022-04-25');
        self::assertSame(8 * 60 * 60, $tz->getOffset($start));
    }

    public function testCustomizedTimeZoneFlag(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $ics = $this->getCustomizedICS();
        $vobject = Reader::read($ics);
        $vobject->VEVENT->DTSTART->getDateTime(null, false);
    }

    private function getCustomizedICS(): string
    {
        return <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//ical.marudot.com//iCal Event Maker
X-WR-CALNAME:Victorian public holiday dates
NAME:Victorian public holiday dates
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Customized Time Zone
LAST-MODIFIED:20211207T194144Z
X-LIC-LOCATION:Customized Time Zone
BEGIN:STANDARD
TZNAME:CST
TZOFFSETFROM:+0800
TZOFFSETTO:+0800
DTSTART:19700101T000000
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20200907T032724Z
UID:problematicTimezone@example.com
DTSTART;TZID=Customized Time Zone:20210611T110000
DTEND;TZID=Customized Time Zone:20210611T113000
SUMMARY:customized time zone
END:VEVENT
END:VCALENDAR
ICS;
    }
}

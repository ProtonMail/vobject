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
    public function testCorrectTZ($timezoneName)
    {
        try {
            $tz = new \DateTimeZone($timezoneName);
            $this->assertInstanceOf('DateTimeZone', $tz);
        } catch (\Exception $e) {
            if (false !== strpos($e->getMessage(), 'Unknown or bad timezone')) {
                $this->markTestSkipped($timezoneName.' is not (yet) supported in this PHP version. Update pecl/timezonedb');
            } else {
                throw $e;
            }
        }
    }

    public function getMapping()
    {
        $map = array_merge(
            include __DIR__.'/../../lib/timezonedata/windowszones.php',
            include __DIR__.'/../../lib/timezonedata/lotuszones.php',
            include __DIR__.'/../../lib/timezonedata/exchangezones.php',
            include __DIR__.'/../../lib/timezonedata/php-workaround.php',
            include __DIR__.'/../../lib/timezonedata/extrazones.php'
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
    public function testSlashTZ($timezonename)
    {
        $slashTimezone = '/'.$timezonename;
        $expected = TimeZoneUtil::getTimeZone($timezonename)->getName();
        $actual = TimeZoneUtil::getTimeZone($slashTimezone)->getName();
        $this->assertEquals($expected, $actual);
    }

    public function testExchangeMap()
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

        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testWhetherMicrosoftIsStillInsane()
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

        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testUnknownExchangeId()
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
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testEmptyTimeZone()
    {
        $tz = TimeZoneUtil::getTimeZone('');
        $ex = new \DateTimeZone('UTC');
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testWindowsTimeZone()
    {
        $tz = TimeZoneUtil::getTimeZone('Eastern Standard Time');
        $ex = new \DateTimeZone('America/New_York');
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testLowerCaseTimeZone()
    {
        $tz = TimeZoneUtil::getTimeZone('mountain time (us & canada)');
        $ex = new \DateTimeZone('America/Denver');
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider getPHPTimeZoneIdentifiers
     */
    public function testTimeZoneIdentifiers($tzid)
    {
        $tz = TimeZoneUtil::getTimeZone($tzid);

        $priorityZones = $this->getPriorityTimeZoneIdentifiers();
        if (isset($priorityZones[$tzid])) {
            $tzid = $priorityZones[$tzid];
        }
        $ex = new \DateTimeZone($tzid);

        $this->assertEquals($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider getPHPTimeZoneBCIdentifiers
     */
    public function testTimeZoneBCIdentifiers($tzid)
    {
        $tz = TimeZoneUtil::getTimeZone($tzid);
        $ex = new \DateTimeZone($tzid);

        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function getPHPTimeZoneIdentifiers()
    {
        // PHPUNit requires an array of arrays
        return array_map(
            function ($value) {
                return [$value];
            },
            \DateTimeZone::listIdentifiers()
        );
    }

    public function getPriorityTimeZoneIdentifiers()
    {
        // PHPUNit requires an array of arrays
        return array_merge(
            include __DIR__.'/../../lib/timezonedata/priorityzones.php',
        );
    }

    public function getPHPTimeZoneBCIdentifiers()
    {
        // PHPUNit requires an array of arrays
        return array_map(
            function ($value) {
                return [$value];
            },
            include __DIR__.'/../../lib/timezonedata/php-bc.php'
        );
    }

    public function testTimezoneOffset()
    {
        $tz = TimeZoneUtil::getTimeZone('GMT-0400', null, true);

        if (version_compare(PHP_VERSION, '5.5.10', '>=') && !defined('HHVM_VERSION')) {
            $ex = new \DateTimeZone('-04:00');
        } else {
            $ex = new \DateTimeZone('Etc/GMT-4');
        }
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testTimezoneFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        $tz = TimeZoneUtil::getTimeZone('FooBar', null, true);
    }

    public function testFallBack()
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
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testLjubljanaBug()
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
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testWeirdSystemVLICs()
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
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testPrefixedOffsetExchangeIdentifier()
    {
        $tz = TimeZoneUtil::getTimeZone('(UTC-05:00) Eastern Time (US & Canada)');
        $ex = new \DateTimeZone('America/New_York');
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    public function testMicrosoftMap()
    {
        $tz = TimeZoneUtil::getTimeZone('tzone://Microsoft/Utc', null, true);
        $ex = new \DateTimeZone('UTC');
        $this->assertEquals($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider unSupportTimezoneProvider
     */
    public function testPHPUnSupportTimeZone(string $origin, string $expected)
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, true);
        $ex = new \DateTimeZone($expected);
        $this->assertEquals($ex->getName(), $tz->getName());
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

        yield 'Pacific/Kanton' => [
            'origin' => 'Pacific/Kanton',
            'expected' => 'Pacific/Fakaofo',
        ];

        yield 'UTC+13' => [
            'origin' => 'UTC+13',
            'expected' => 'Pacific/Fakaofo',
        ];

        yield 'Pacific/Enderbury' => [
            'origin' => 'Pacific/Enderbury',
            'expected' => 'Pacific/Fakaofo',
        ];

        if (($handle = fopen(__DIR__ . "/microsoft-timezones-confluence.csv", "r")) !== FALSE) {
            $data = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== FALSE) {
                yield $data[0] => [
                    'origin' => $data[0],
                    'expected' => $data[2] !== '' ? $data[2] : $data[1],
                ];
            }
            fclose($handle);
        }
    }

    /**
     * @dataProvider offsetTimeZoneProvider
     */
    public function testOffsetTimeZones(string $origin, string $expected)
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, true);
        $ex = new \DateTimeZone($expected);
        $this->assertEquals($ex->getName(), $tz->getName());
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
    public function testDifferentLetterCaseTimeZone(string $origin, string $expected)
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, true);
        $ex = new \DateTimeZone($expected);
        $this->assertEquals($ex->getName(), $tz->getName());
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
    public function testOutlookCities(string $origin, bool $failIfUncertain, string $expected)
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, $failIfUncertain);
        $ex = new \DateTimeZone($expected);
        $this->assertEquals($ex->getName(), $tz->getName());
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
    public function testVersionTz(string $origin, bool $failIfUncertain, string $expected)
    {
        $tz = TimeZoneUtil::getTimeZone($origin, null, $failIfUncertain);
        $ex = new \DateTimeZone($expected);
        $this->assertEquals($ex->getName(), $tz->getName());
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

    public function testCustomizedTimeZone()
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
        $this->assertNotSame('Customized Time Zone', $tz->getName());
        $start = new \DateTimeImmutable('2022-04-25');
        $this->assertSame(10 * 60 * 60, $tz->getOffset($start));

        $start = new \DateTimeImmutable('2022-11-10');
        $this->assertSame(11 * 60 * 60, $tz->getOffset($start));
    }

    public function testCustomizedTimeZoneWithoutDaylight()
    {
        $ics = $this->getCustomizedICS();
        $tz = TimeZoneUtil::getTimeZone('Customized Time Zone', Reader::read($ics));
        $this->assertSame('Asia/Brunei', $tz->getName());
        $start = new \DateTimeImmutable('2022-04-25');
        $this->assertSame(8 * 60 * 60, $tz->getOffset($start));
    }

    public function testCustomizedTimeZoneFlag()
    {
        $this->expectException(\InvalidArgumentException::class);
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

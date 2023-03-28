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

    public function testDeprecatedTimeZone()
    {
        // Deprecated in 2022b
        $tz = TimeZoneUtil::getTimeZone('Europe/Kiev');
        $ex = new \DateTimeZone('Europe/Kiev');
        $this->assertSame($ex->getName(), $tz->getName());
    }

    public function testDeprecatedUnsupportedTimeZone()
    {
        // Deprecated and unsupported
        $tz = TimeZoneUtil::getTimeZone('America/Godthab');
        $ex = new \DateTimeZone('America/Godthab');
        $this->assertNotSame($ex->getName(), $tz->getName());
    }

    /**
     * @dataProvider getPHPTimeZoneIdentifiers
     */
    public function testTimeZoneIdentifiers($tzid)
    {
        $tz = TimeZoneUtil::getTimeZone($tzid);
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

        $microsoftTimezones = <<<JSON
{
  "abu dhabi, muscat": "Asia/Dubai",
  "acre": "America/Rio_Branco",
  "adelaide, central australia": "Australia/Adelaide",
  "afghanistan": "Asia/Kabul",
  "afghanistan standard time": "Asia/Kabul",
  "africa central": "Africa/Maputo",
  "africa eastern": "Africa/Nairobi",
  "africa farwestern": "Africa/El_Aaiun",
  "africa southern": "Africa/Johannesburg",
  "africa western": "Africa/Lagos",
  "aktyubinsk": "Asia/Aqtobe",
  "alaska": "America/Anchorage",
  "alaska hawaii": "America/Anchorage",
  "alaskan": "America/Anchorage",
  "alaskan standard time": "America/Anchorage",
  "aleutian standard time": "America/Adak",
  "almaty": "Asia/Almaty",
  "almaty, novosibirsk, north central asia": "Asia/Almaty",
  "altai standard time": "Asia/Barnaul",
  "amazon": "America/Manaus",
  "america central": "America/Chicago",
  "america eastern": "America/New_York",
  "america mountain": "America/Denver",
  "america pacific": "America/Los_Angeles",
  "amsterdam, berlin, bern, rome, stockholm, vienna": "Europe/Berlin",
  "anadyr": "Asia/Anadyr",
  "apia": "Pacific/Apia",
  "aqtau": "Asia/Aqtau",
  "aqtobe": "Asia/Aqtobe",
  "arab": "Asia/Riyadh",
  "arab standard time": "Asia/Riyadh",
  "arab, kuwait, riyadh": "Asia/Riyadh",
  "arabian": "Asia/Dubai",
  "arabian standard time": "Asia/Dubai",
  "arabic": "Asia/Baghdad",
  "arabic standard time": "Asia/Baghdad",
  "argentina": "America/Argentina/Buenos_Aires",
  "argentina standard time": "America/Argentina/Buenos_Aires",
  "argentina western": "America/Argentina/San_Luis",
  "arizona": "America/Phoenix",
  "armenia": "Asia/Yerevan",
  "armenian": "Asia/Yerevan",
  "armenian standard time": "Asia/Yerevan",
  "ashkhabad": "Asia/Ashgabat",
  "astana, dhaka": "Asia/Dhaka",
  "astrakhan standard time": "Europe/Astrakhan",
  "athens, istanbul, minsk": "Europe/Athens",
  "atlantic": "America/Halifax",
  "atlantic standard time": "America/Halifax",
  "atlantic time (canada)": "America/Halifax",
  "auckland, wellington": "Pacific/Auckland",
  "aus central": "Australia/Darwin",
  "aus central standard time": "Australia/Darwin",
  "aus central w standard time": "Australia/Eucla",
  "aus eastern": "Australia/Sydney",
  "aus eastern standard time": "Australia/Sydney",
  "australia central": "Australia/Adelaide",
  "australia centralwestern": "Australia/Eucla",
  "australia eastern": "Australia/Sydney",
  "australia western": "Australia/Perth",
  "azerbaijan": "Asia/Baku",
  "azerbaijan standard time": "Asia/Baku",
  "azerbijan": "Asia/Baku",
  "azores": "Atlantic/Azores",
  "azores standard time": "Atlantic/Azores",
  "baghdad": "Asia/Baghdad",
  "bahia standard time": "America/Bahia",
  "baku": "Asia/Baku",
  "baku, tbilisi, yerevan": "Asia/Baku",
  "bangkok, hanoi, jakarta": "Asia/Bangkok",
  "bangladesh": "Asia/Dhaka",
  "bangladesh standard time": "Asia/Dhaka",
  "beijing, chongqing, hong kong sar, urumqi": "Asia/Shanghai",
  "belarus standard time": "Europe/Minsk",
  "belgrade, pozsony, budapest, ljubljana, prague": "Europe/Prague",
  "bering": "America/Adak",
  "bhutan": "Asia/Thimphu",
  "bogota, lima, quito": "America/Bogota",
  "bolivia": "America/La_Paz",
  "borneo": "Asia/Kuching",
  "bougainville standard time": "Pacific/Bougainville",
  "brasilia": "America/Sao_Paulo",
  "brisbane, east australia": "Australia/Brisbane",
  "british": "Europe/London",
  "brunei": "Asia/Kuching",
  "brussels, copenhagen, madrid, paris": "Europe/Paris",
  "bucharest": "Europe/Bucharest",
  "buenos aires": "America/Argentina/Buenos_Aires",
  "cairo": "Africa/Cairo",
  "canada central": "America/Edmonton",
  "canada central standard time": "America/Regina",
  "canberra, melbourne, sydney, hobart (year 2000 only)": "Australia/Sydney",
  "cape verde": "Atlantic/Cape_Verde",
  "cape verde is": "Atlantic/Cape_Verde",
  "cape verde standard time": "Atlantic/Cape_Verde",
  "caracas, la paz": "America/Caracas",
  "casablanca, monrovia": "Africa/Casablanca",
  "casey": "Antarctica/Casey",
  "caucasus": "Asia/Yerevan",
  "caucasus standard time": "Asia/Yerevan",
  "cen australia": "Australia/Adelaide",
  "cen australia standard time": "Australia/Adelaide",
  "central": "America/Chicago",
  "central america": "America/Guatemala",
  "central america standard time": "America/Guatemala",
  "central asia": "Asia/Dhaka",
  "central asia standard time": "Asia/Almaty",
  "central brazilian": "America/Manaus",
  "central brazilian standard time": "America/Cuiaba",
  "central europe": "Europe/Prague",
  "central europe standard time": "Europe/Budapest",
  "central european": "Europe/Belgrade",
  "central european standard time": "Europe/Warsaw",
  "central pacific": "Asia/Magadan",
  "central pacific standard time": "Pacific/Guadalcanal",
  "central standard time": "America/Chicago",
  "central standard time (mexico)": "America/Mexico_City",
  "central time (us & canada)": "America/Chicago",
  "chamorro": "Pacific/Guam",
  "chatham": "Pacific/Chatham",
  "chatham islands standard time": "Pacific/Chatham",
  "chile": "America/Santiago",
  "china": "Asia/Shanghai",
  "china standard time": "Asia/Shanghai",
  "choibalsan": "Asia/Choibalsan",
  "christmas": "Asia/Bangkok",
  "cocos": "Asia/Yangon",
  "colombia": "America/Bogota",
  "cook": "Pacific/Rarotonga",
  "cuba": "America/Havana",
  "cuba standard time": "America/Havana",
  "dacca": "Asia/Dhaka",
  "darwin": "Australia/Darwin",
  "dateline": "Pacific/Auckland",
  "dateline standard time": "Pacific/Niue",
  "davis": "Antarctica/Davis",
  "dominican": "America/Santo_Domingo",
  "dumontdurville": "Pacific/Port_Moresby",
  "dushanbe": "Asia/Dushanbe",
  "dutch guiana": "America/Paramaribo",
  "e africa": "Africa/Nairobi",
  "e africa standard time": "Africa/Nairobi",
  "e australia": "Australia/Brisbane",
  "e australia standard time": "Australia/Brisbane",
  "e europe": "Europe/Minsk",
  "e europe standard time": "Europe/Chisinau",
  "e south america": "America/Belem",
  "e south america standard time": "America/Sao_Paulo",
  "east africa, nairobi": "Africa/Nairobi",
  "east timor": "Asia/Dili",
  "easter": "Pacific/Easter",
  "easter island standard time": "Pacific/Easter",
  "eastern": "America/New_York",
  "eastern standard time": "America/New_York",
  "eastern standard time (mexico)": "America/Cancun",
  "eastern time (us & canada)": "America/New_York",
  "ecuador": "America/Guayaquil",
  "egypt": "Africa/Cairo",
  "egypt standard time": "Africa/Cairo",
  "ekaterinburg": "Asia/Yekaterinburg",
  "ekaterinburg standard time": "Asia/Yekaterinburg",
  "eniwetok, kwajalein, dateline time": "Pacific/Kwajalein",
  "europe central": "Europe/Paris",
  "europe eastern": "Europe/Bucharest",
  "europe further eastern": "Europe/Minsk",
  "europe western": "Atlantic/Canary",
  "falkland": "Atlantic/Stanley",
  "fiji": "Pacific/Fiji",
  "fiji islands standard time": "Pacific/Fiji",
  "fiji islands, kamchatka, marshall is": "Pacific/Fiji",
  "fiji standard time": "Pacific/Fiji",
  "fle": "Europe/Helsinki",
  "fle standard time": "Europe/Kyiv",
  "french guiana": "America/Cayenne",
  "french southern": "Indian/Maldives",
  "frunze": "Asia/Bishkek",
  "galapagos": "Pacific/Galapagos",
  "gambier": "Pacific/Gambier",
  "georgia": "Asia/Tbilisi",
  "georgian": "Asia/Tbilisi",
  "georgian standard time": "Asia/Tbilisi",
  "gilbert islands": "Pacific/Tarawa",
  "gmt": "Europe/London",
  "gmt standard time": "Europe/London",
  "goose bay": "America/Goose_Bay",
  "greenland": "America/Nuuk",
  "greenland central": "America/Scoresbysund",
  "greenland eastern": "America/Scoresbysund",
  "greenland standard time": "America/Nuuk",
  "greenland western": "America/Nuuk",
  "greenwich": "Africa/Abidjan",
  "greenwich mean time; dublin, edinburgh, london": "Europe/London",
  "greenwich mean time: dublin, edinburgh, lisbon, london": "Europe/Lisbon",
  "greenwich standard time": "Africa/Abidjan",
  "gtb": "Europe/Athens",
  "gtb standard time": "Europe/Bucharest",
  "guam": "Pacific/Guam",
  "guam, port moresby": "Pacific/Guam",
  "gulf": "Asia/Dubai",
  "guyana": "America/Guyana",
  "haiti standard time": "America/Port-au-Prince",
  "harare, pretoria": "Africa/Maputo",
  "hawaii": "Pacific/Honolulu",
  "hawaii aleutian": "Pacific/Honolulu",
  "hawaiian": "Pacific/Honolulu",
  "hawaiian standard time": "Pacific/Honolulu",
  "helsinki, riga, tallinn": "Europe/Helsinki",
  "hobart, tasmania": "Australia/Hobart",
  "hong kong": "Asia/Hong_Kong",
  "hovd": "Asia/Hovd",
  "india": "Asia/Kolkata",
  "india standard time": "Asia/Kolkata",
  "indian ocean": "Indian/Chagos",
  "indiana (east)": "America/Indiana/Indianapolis",
  "indochina": "Asia/Bangkok",
  "indonesia central": "Asia/Makassar",
  "indonesia eastern": "Asia/Jayapura",
  "indonesia western": "Asia/Jakarta",
  "iran": "Asia/Tehran",
  "iran standard time": "Asia/Tehran",
  "irish": "Europe/Dublin",
  "irkutsk": "Asia/Irkutsk",
  "irkutsk, ulaan bataar": "Asia/Irkutsk",
  "islamabad, karachi, tashkent": "Asia/Karachi",
  "israel": "Asia/Jerusalem",
  "israel standard time": "Asia/Jerusalem",
  "israel, jerusalem standard time": "Asia/Jerusalem",
  "japan": "Asia/Tokyo",
  "jordan": "Asia/Amman",
  "jordan standard time": "Asia/Amman",
  "kabul": "Asia/Kabul",
  "kaliningrad standard time": "Europe/Kaliningrad",
  "kamchatka": "Asia/Kamchatka",
  "kamchatka standard time": "Asia/Kamchatka",
  "karachi": "Asia/Karachi",
  "kathmandu, nepal": "Asia/Kathmandu",
  "kazakhstan eastern": "Asia/Almaty",
  "kazakhstan western": "Asia/Aqtobe",
  "kizilorda": "Asia/Qyzylorda",
  "kolkata, chennai, mumbai, new delhi, india standard time": "Asia/Kolkata",
  "korea": "Asia/Seoul",
  "korea standard time": "Asia/Seoul",
  "kosrae": "Pacific/Kosrae",
  "krasnoyarsk": "Asia/Krasnoyarsk",
  "kuala lumpur, singapore": "Asia/Singapore",
  "kuybyshev": "Europe/Samara",
  "kwajalein": "Pacific/Kwajalein",
  "kyrgystan": "Asia/Bishkek",
  "lanka": "Asia/Colombo",
  "liberia": "Africa/Monrovia",
  "libya standard time": "Africa/Tripoli",
  "line islands": "Pacific/Kiritimati",
  "line islands standard time": "Pacific/Kiritimati",
  "lord howe": "Australia/Lord_Howe",
  "lord howe standard time": "Australia/Lord_Howe",
  "macau": "Asia/Macau",
  "macquarie": "Antarctica/Macquarie",
  "magadan": "Asia/Magadan",
  "magadan standard time": "Asia/Magadan",
  "magadan, solomon is, new caledonia": "Asia/Magadan",
  "magallanes standard time": "America/Punta_Arenas",
  "malaya": "Asia/Singapore",
  "malaysia": "Asia/Kuching",
  "maldives": "Indian/Maldives",
  "marquesas": "Pacific/Marquesas",
  "marquesas standard time": "Pacific/Marquesas",
  "marshall islands": "Pacific/Tarawa",
  "mauritius": "Indian/Mauritius",
  "mauritius standard time": "Indian/Mauritius",
  "mawson": "Antarctica/Mawson",
  "mexico": "America/Mexico_City",
  "mexico city, tegucigalpa": "America/Mexico_City",
  "mexico pacific": "America/Mazatlan",
  "mexico standard time": "America/Mexico_City",
  "mexico standard time 2": "America/Chihuahua",
  "mid-atlantic": "America/Noronha",
  "mid-atlantic standard time": "Atlantic/Cape_Verde",
  "middle east": "Asia/Beirut",
  "middle east standard time": "Asia/Beirut",
  "midway island, samoa": "Pacific/Pago_Pago",
  "mongolia": "Asia/Ulaanbaatar",
  "montevideo": "America/Montevideo",
  "montevideo standard time": "America/Montevideo",
  "morocco": "Africa/Casablanca",
  "morocco standard time": "Africa/Casablanca",
  "moscow": "Europe/Moscow",
  "moscow, st petersburg, volgograd": "Europe/Moscow",
  "mountain": "America/Denver",
  "mountain standard time": "America/Denver",
  "mountain standard time (mexico)": "America/Chihuahua",
  "mountain time (us & canada)": "America/Denver",
  "myanmar": "Asia/Yangon",
  "myanmar standard time": "Asia/Yangon",
  "n central asia": "Asia/Almaty",
  "n central asia standard time": "Asia/Novosibirsk",
  "namibia": "Africa/Windhoek",
  "namibia standard time": "Africa/Windhoek",
  "nauru": "Pacific/Nauru",
  "nepal": "Asia/Kathmandu",
  "nepal standard time": "Asia/Kathmandu",
  "new caledonia": "Pacific/Noumea",
  "new zealand": "Pacific/Auckland",
  "new zealand standard time": "Pacific/Auckland",
  "newfoundland": "America/St_Johns",
  "newfoundland and labrador standard time": "America/St_Johns",
  "newfoundland standard time": "America/St_Johns",
  "niue": "Pacific/Niue",
  "norfolk": "Pacific/Norfolk",
  "norfolk standard time": "Pacific/Norfolk",
  "noronha": "America/Noronha",
  "north asia": "Asia/Krasnoyarsk",
  "north asia east": "Asia/Irkutsk",
  "north asia east standard time": "Asia/Irkutsk",
  "north asia standard time": "Asia/Krasnoyarsk",
  "north korea standard time": "Asia/Pyongyang",
  "north mariana": "Pacific/Guam",
  "novosibirsk": "Asia/Novosibirsk",
  "nuku'alofa, tonga": "Pacific/Tongatapu",
  "omsk": "Asia/Omsk",
  "omsk standard time": "Asia/Omsk",
  "oral": "Asia/Oral",
  "osaka, sapporo, tokyo": "Asia/Tokyo",
  "pacific": "America/Los_Angeles",
  "pacific sa": "America/Santiago",
  "pacific sa standard time": "America/Santiago",
  "pacific standard time": "America/Los_Angeles",
  "pacific standard time (mexico)": "America/Tijuana",
  "pacific time (us & canada)": "America/Los_Angeles",
  "pacific time (us & canada); tijuana": "America/Los_Angeles",
  "pakistan": "Asia/Karachi",
  "pakistan standard time": "Asia/Karachi",
  "palau": "Pacific/Palau",
  "papua new guinea": "Pacific/Port_Moresby",
  "paraguay": "America/Asuncion",
  "paraguay standard time": "America/Asuncion",
  "paris, madrid, brussels, copenhagen": "Europe/Paris",
  "perth, western australia": "Australia/Perth",
  "peru": "America/Lima",
  "philippines": "Asia/Manila",
  "phoenix islands": "Pacific/Kanton",
  "pierre miquelon": "America/Miquelon",
  "pitcairn": "Pacific/Pitcairn",
  "prague, central europe": "Europe/Prague",
  "pyongyang": "Asia/Pyongyang",
  "qyzylorda": "Asia/Qyzylorda",
  "qyzylorda standard time": "Asia/Qyzylorda",
  "rangoon": "Asia/Yangon",
  "reunion": "Asia/Dubai",
  "romance": "Europe/Paris",
  "romance standard time": "Europe/Paris",
  "rothera": "Antarctica/Rothera",
  "russia time zone 10": "Asia/Srednekolymsk",
  "russia time zone 11": "Asia/Kamchatka",
  "russia time zone 3": "Europe/Samara",
  "russian": "Europe/Moscow",
  "russian standard time": "Europe/Moscow",
  "sa eastern": "America/Belem",
  "sa eastern standard time": "America/Cayenne",
  "sa pacific": "America/Bogota",
  "sa pacific standard time": "America/Bogota",
  "sa western": "America/La_Paz",
  "sa western standard time": "America/La_Paz",
  "saint pierre standard time": "America/Miquelon",
  "sakhalin": "Asia/Sakhalin",
  "sakhalin standard time": "Asia/Sakhalin",
  "samara": "Europe/Samara",
  "samarkand": "Asia/Samarkand",
  "samoa": "Pacific/Apia",
  "samoa standard time": "Pacific/Apia",
  "santiago": "America/Santiago",
  "sao tome standard time": "Africa/Sao_Tome",
  "sarajevo, skopje, sofija, vilnius, warsaw, zagreb": "Europe/Sofia",
  "saratov standard time": "Europe/Saratov",
  "saskatchewan": "America/Edmonton",
  "se asia": "Asia/Bangkok",
  "se asia standard time": "Asia/Bangkok",
  "seoul, korea standard time": "Asia/Seoul",
  "seychelles": "Asia/Dubai",
  "shevchenko": "Asia/Aqtau",
  "singapore": "Asia/Singapore",
  "singapore standard time": "Asia/Singapore",
  "solomon": "Pacific/Guadalcanal",
  "south africa": "Africa/Maputo",
  "south africa standard time": "Africa/Johannesburg",
  "south georgia": "Atlantic/South_Georgia",
  "sri jayawardenepura, sri lanka": "Asia/Colombo",
  "sri lanka": "Asia/Colombo",
  "sri lanka standard time": "Asia/Colombo",
  "sudan standard time": "Africa/Khartoum",
  "suriname": "America/Paramaribo",
  "sverdlovsk": "Asia/Yekaterinburg",
  "syowa": "Asia/Riyadh",
  "syria standard time": "Asia/Damascus",
  "tahiti": "Pacific/Tahiti",
  "taipei": "Asia/Taipei",
  "taipei standard time": "Asia/Taipei",
  "tajikistan": "Asia/Dushanbe",
  "tashkent": "Asia/Tashkent",
  "tasmania": "Australia/Hobart",
  "tasmania standard time": "Australia/Hobart",
  "tbilisi": "Asia/Tbilisi",
  "tehran": "Asia/Tehran",
  "tocantins standard time": "America/Araguaina",
  "tokelau": "Pacific/Fakaofo",
  "tokyo": "Asia/Tokyo",
  "tokyo standard time": "Asia/Tokyo",
  "tomsk standard time": "Asia/Tomsk",
  "tonga": "Pacific/Tongatapu",
  "tonga standard time": "Pacific/Tongatapu",
  "transbaikal standard time": "Asia/Chita",
  "transitional islamic state of afghanistan standard time": "Asia/Kabul",
  "turkey": "Europe/Istanbul",
  "turkey standard time": "Europe/Istanbul",
  "turkmenistan": "Asia/Ashgabat",
  "turks and caicos standard time": "America/Grand_Turk",
  "tuvalu": "Pacific/Tarawa",
  "ulaanbaatar standard time": "Asia/Ulaanbaatar",
  "universal coordinated time": "UTC",
  "uralsk": "Asia/Oral",
  "uruguay": "America/Montevideo",
  "urumqi": "Asia/Urumqi",
  "us eastern": "America/Indiana/Indianapolis",
  "us eastern standard time": "America/New_York",
  "us mountain": "America/Phoenix",
  "us mountain standard time": "America/Phoenix",
  "utc-02": "America/Noronha",
  "utc-08": "Pacific/Pitcairn",
  "utc-09": "Pacific/Gambier",
  "utc-11": "Pacific/Niue",
  "utc+12": "Pacific/Auckland",
  "uzbekistan": "Asia/Tashkent",
  "vanuatu": "Pacific/Efate",
  "venezuela": "America/Caracas",
  "venezuela standard time": "America/Caracas",
  "vladivostok": "Asia/Vladivostok",
  "vladivostok standard time": "Asia/Vladivostok",
  "volgograd": "Europe/Volgograd",
  "volgograd standard time": "Europe/Volgograd",
  "vostok": "Asia/Urumqi",
  "w australia": "Australia/Perth",
  "w australia standard time": "Australia/Perth",
  "w central africa": "Africa/Lagos",
  "w central africa standard time": "Africa/Lagos",
  "w europe": "Europe/Brussels",
  "w europe standard time": "Europe/Berlin",
  "w mongolia standard time": "Asia/Hovd",
  "wake": "Pacific/Tarawa",
  "wallis": "Pacific/Tarawa",
  "west asia": "Asia/Tashkent",
  "west asia standard time": "Asia/Tashkent",
  "west bank standard time": "Asia/Hebron",
  "west central africa": "Africa/Lagos",
  "west pacific": "Pacific/Guam",
  "west pacific standard time": "Pacific/Port_Moresby",
  "yakutsk": "Asia/Yakutsk",
  "yakutsk standard time": "Asia/Yakutsk",
  "yekaterinburg": "Asia/Yekaterinburg",
  "yerevan": "Asia/Yerevan",
  "yukon": "America/Yakutat",
  "coordinated universal time-11": "Pacific/Pago_Pago",
  "aleutian islands": "America/Adak",
  "marquesas islands": "Pacific/Marquesas",
  "coordinated universal time-09": "America/Anchorage",
  "baja california": "America/Tijuana",
  "coordinated universal time-08": "Pacific/Pitcairn",
  "chihuahua, la paz, mazatlan": "America/Chihuahua",
  "easter island": "Pacific/Easter",
  "guadalajara, mexico city, monterrey": "America/Mexico_City",
  "bogota, lima, quito, rio branco": "America/Bogota",
  "chetumal": "America/Cancun",
  "haiti": "America/Port-au-Prince",
  "havana": "America/Havana",
  "turks and caicos": "America/Grand_Turk",
  "asuncion": "America/Asuncion",
  "caracas": "America/Caracas",
  "cuiaba": "America/Cuiaba",
  "georgetown, la paz, manaus, san juan": "America/La_Paz",
  "araguaina": "America/Araguaina",
  "cayenne, fortaleza": "America/Cayenne",
  "city of buenos aires": "America/Argentina/Buenos_Aires",
  "punta arenas": "America/Punta_Arenas",
  "saint pierre and miquelon": "America/Miquelon",
  "salvador": "America/Bahia",
  "coordinated universal time-02": "America/Noronha",
  "mid-atlantic - old": "America/Noronha",
  "cabo verde is": "Atlantic/Cape_Verde",
  "coordinated universal time": "UTC",
  "dublin, edinburgh, lisbon, london": "Europe/London",
  "monrovia, reykjavik": "Africa/Abidjan",
  "belgrade, bratislava, budapest, ljubljana, prague": "Europe/Budapest",
  "casablanca": "Africa/Casablanca",
  "sao tome": "Africa/Sao_Tome",
  "sarajevo, skopje, warsaw, zagreb": "Europe/Warsaw",
  "amman": "Asia/Amman",
  "athens, bucharest": "Europe/Bucharest",
  "beirut": "Asia/Beirut",
  "chisinau": "Europe/Chisinau",
  "damascus": "Asia/Damascus",
  "gaza, hebron": "Asia/Hebron",
  "jerusalem": "Asia/Jerusalem",
  "kaliningrad": "Europe/Kaliningrad",
  "khartoum": "Africa/Khartoum",
  "tripoli": "Africa/Tripoli",
  "windhoek": "Africa/Windhoek",
  "istanbul": "Europe/Istanbul",
  "kuwait, riyadh": "Asia/Riyadh",
  "minsk": "Europe/Minsk",
  "moscow, st petersburg": "Europe/Moscow",
  "nairobi": "Africa/Nairobi",
  "astrakhan, ulyanovsk": "Europe/Astrakhan",
  "izhevsk, samara": "Europe/Samara",
  "port louis": "Indian/Mauritius",
  "saratov": "Europe/Saratov",
  "ashgabat, tashkent": "Asia/Tashkent",
  "islamabad, karachi": "Asia/Karachi",
  "chennai, kolkata, mumbai, new delhi": "Asia/Kolkata",
  "sri jayawardenepura": "Asia/Colombo",
  "kathmandu": "Asia/Kathmandu",
  "astana": "Asia/Almaty",
  "dhaka": "Asia/Dhaka",
  "yangon (rangoon)": "Asia/Yangon",
  "barnaul, gorno-altaysk": "Asia/Barnaul",
  "tomsk": "Asia/Tomsk",
  "beijing, chongqing, hong kong, urumqi": "Asia/Shanghai",
  "perth": "Australia/Perth",
  "ulaanbaatar": "Asia/Ulaanbaatar",
  "eucla": "Australia/Eucla",
  "chita": "Asia/Chita",
  "seoul": "Asia/Seoul",
  "adelaide": "Australia/Adelaide",
  "brisbane": "Australia/Brisbane",
  "canberra, melbourne, sydney": "Australia/Sydney",
  "hobart": "Australia/Hobart",
  "lord howe island": "Australia/Lord_Howe",
  "bougainville island": "Pacific/Bougainville",
  "chokurdakh": "Asia/Srednekolymsk",
  "norfolk island": "Pacific/Norfolk",
  "solomon is, new caledonia": "Pacific/Guadalcanal",
  "anadyr, petropavlovsk-kamchatsky": "Asia/Kamchatka",
  "coordinated universal time+12": "Pacific/Tarawa",
  "petropavlovsk-kamchatsky - old": "Asia/Anadyr",
  "chatham islands": "Pacific/Chatham",
  "coordinated universal time+13": "Pacific/Kanton",
  "nuku'alofa": "Pacific/Tongatapu",
  "kiritimati island": "Pacific/Kiritimati",
  "helsinki, kyiv, riga, sofia, tallinn, vilnius": "Europe/Helsinki",
  "amsterdam, berlin, berne, rome, stockholm, vienne": "Europe/Berlin"
}
JSON;

        $timezones = json_decode($microsoftTimezones, true);
        foreach ($timezones as $origin => $expected) {
            yield $origin => [
                'origin' => $origin,
                'expected' => $expected,
            ];
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

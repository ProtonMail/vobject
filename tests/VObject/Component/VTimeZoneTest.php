<?php

namespace Sabre\VObject\Component;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

class VTimeZoneTest extends TestCase
{
    public function testValidate(): void
    {
        $input = <<<HI
BEGIN:VCALENDAR
VERSION:2.0
PRODID:YoYo
BEGIN:VTIMEZONE
TZID:America/Toronto
END:VTIMEZONE
END:VCALENDAR
HI;

        $obj = Reader::read($input);

        $warnings = $obj->validate();
        $messages = [];
        foreach ($warnings as $warning) {
            $messages[] = $warning['message'];
        }

        self::assertEquals([], $messages);
    }

    public function testGetTimeZone(): void
    {
        $input = <<<HI
BEGIN:VCALENDAR
VERSION:2.0
PRODID:YoYo
BEGIN:VTIMEZONE
TZID:America/Toronto
END:VTIMEZONE
END:VCALENDAR
HI;

        $obj = Reader::read($input);

        $tz = new \DateTimeZone('America/Toronto');

        self::assertEquals(
            $tz,
            $obj->VTIMEZONE->getTimeZone()
        );
    }

    public function testGetEmptyTimeZone()
    {
        $input = <<<HI
BEGIN:VCALENDAR
VERSION:2.0
PRODID:YoYo
BEGIN:VTIMEZONE
END:VTIMEZONE
END:VCALENDAR
HI;

        $obj = Reader::read($input);

        $tz = new \DateTimeZone(date_default_timezone_get());

        $this->assertEquals(
            $tz,
            $obj->VTIMEZONE->getTimeZone()
        );
    }
}

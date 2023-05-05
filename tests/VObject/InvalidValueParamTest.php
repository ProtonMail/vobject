<?php

namespace Sabre\VObject;

use PHPUnit\Framework\TestCase;

class InvalidValueParamTest extends TestCase
{
    public function testWorkaround()
    {
        // See https://github.com/fruux/sabre-vobject/issues/36
        $event = <<<ICS
            BEGIN:VCALENDAR
            VERSION:2.0
            BEGIN:VEVENT
            DTEND;TZID=Europe/Paris:20170530T220000
            DTSTAMP:20230317T130521Z
            DTSTART;TZID=Europe/Paris:20170530T200000
            LAST-MODIFIED:20230316T155811Z
            LOCATION;VALUE=ERROR:ERROR
            SEQUENCE:0
            STATUS:CONFIRMED
            SUMMARY:AG MP3
            UID:0171706E-00F4-4846-8B5F-7FBD474A90AC
            END:VEVENT
            END:VCALENDAR
            ICS;

        $doc = Reader::read($event);
        $this->assertEquals('LOCATION:ERROR', $doc->VEVENT->LOCATION->serialize());
    }
}

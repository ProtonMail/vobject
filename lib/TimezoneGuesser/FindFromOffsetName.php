<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

class FindFromOffsetName implements TimezoneFinder
{
    public static array $offsetTimezones = [
        '+01:00' => 'Africa/Lagos',
        '+02:00' => 'Africa/Cairo',
        '+03:00' => 'Europe/Moscow',
        '+04:00' => 'Asia/Dubai',
        '+05:00' => 'Asia/Karachi',
        '+06:00' => 'Asia/Dhaka',
        '+07:00' => 'Asia/Jakarta',
        '+08:00' => 'Asia/Shanghai',
        '+09:00' => 'Asia/Tokyo',
        '+10:00' => 'Australia/Sydney',
        '+11:00' => 'Pacific/Noumea',
        '+12:00' => 'Pacific/Auckland',
        '+13:00' => 'Pacific/Apia',
        '-01:00' => 'Atlantic/Cape_Verde',
        '-02:00' => 'Atlantic/South_Georgia',
        '-03:00' => 'America/Sao_Paulo',
        '-04:00' => 'America/Manaus',
        '-05:00' => 'America/Lima',
        '-06:00' => 'America/Guatemala',
        '-07:00' => 'America/Hermosillo',
        '-08:00' => 'America/Los_Angeles',
        '-09:00' => 'Pacific/Gambier',
        '-10:00' => 'America/Anchorage',
        '-11:00' => 'Pacific/Niue',
    ];

    public function find(string $tzid, ?bool $failIfUncertain = false): ?\DateTimeZone
    {
        // only handle number timezone
        if (strlen($tzid) > 6) {
            return null;
        }

        try {
            $tzid = new \DateTimeZone($tzid);

            return new \DateTimeZone(self::$offsetTimezones[$tzid->getName()]) ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

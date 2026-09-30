<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FindFromTimezoneMapTest extends TestCase
{
    /**
     * Verify that previously-deprecated IANA names have been replaced with
     * their canonical successors and resolve correctly.
     */
    #[DataProvider('updatedTimezoneProvider')]
    public function testUpdatedTimezonesResolve(string $mapKey, string $expectedOlson): void
    {
        $finder = new FindFromTimezoneMap();
        $tz = $finder->find($mapKey);

        self::assertNotNull($tz, "Expected '$mapKey' to resolve to '$expectedOlson'");
        self::assertSame($expectedOlson, $tz->getName());
    }

    public static function updatedTimezoneProvider(): array
    {
        return [
            // windowszones.php
            // Proton redirects Europe/Kyiv to Europe/Kiev, see FindFromTimezoneIdentifier::MIGRATION_TIMEZONES
            ['FLE Standard Time', 'Europe/Kiev'],
            ['India Standard Time', 'Asia/Kolkata'],
            ['Nepal Standard Time', 'Asia/Kathmandu'],
            ['Myanmar Standard Time', 'Asia/Yangon'],
            // Proton maps Greenland to Atlantic/Stanley (#65)
            ['Greenland Standard Time', 'Atlantic/Stanley'],
            ['Argentina Standard Time', 'America/Argentina/Buenos_Aires'],
            // extrazones.php overrides the windowszones.php value (#14)
            ['US Eastern Standard Time', 'America/New_York'],
            // lotuszones.php
            ['India', 'Asia/Kolkata'],
            ['Myanmar', 'Asia/Yangon'],
            // exchangezones.php
            ['Kolkata, Chennai, Mumbai, New Delhi, India Standard Time', 'Asia/Kolkata'],
            ['Rangoon', 'Asia/Yangon'],
        ];
    }

    /**
     * Verify that the Microsoft-offset-prefix stripping path still works
     * with updated timezone values.
     */
    public function testMicrosoftOffsetPrefixStripping(): void
    {
        $finder = new FindFromTimezoneMap();
        $tz = $finder->find('(UTC+02:00) FLE Standard Time');

        self::assertNotNull($tz);
        self::assertSame('Europe/Kiev', $tz->getName());
    }

    public function testUnknownTimezoneReturnsNull(): void
    {
        $finder = new FindFromTimezoneMap();

        self::assertNull($finder->find('This/Does_Not_Exist'));
    }
}

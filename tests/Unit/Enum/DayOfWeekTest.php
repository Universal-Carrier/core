<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Enum\DayOfWeek;

#[CoversClass(DayOfWeek::class)]
class DayOfWeekTest extends TestCase
{
    public function testItFollowsTheIso8601Numbering(): void
    {
        $this->assertSame(1, DayOfWeek::MONDAY->value);
        $this->assertSame(7, DayOfWeek::SUNDAY->value);
    }

    #[DataProvider('dateProvider')]
    public function testItDerivesTheWeekdayFromADate(string $date, DayOfWeek $expected): void
    {
        $this->assertSame($expected, DayOfWeek::fromDate(new DateTimeImmutable($date)));
    }

    /**
     * @return iterable<string, array{string, DayOfWeek}>
     */
    public static function dateProvider(): iterable
    {
        yield 'monday' => ['2026-09-07', DayOfWeek::MONDAY];
        yield 'saturday' => ['2026-09-12', DayOfWeek::SATURDAY];
        yield 'sunday' => ['2026-09-13', DayOfWeek::SUNDAY];
    }

    public function testItMatchesThePhpDateFormat(): void
    {
        $date = new DateTimeImmutable('2026-09-07');

        $this->assertSame((int)$date->format('N'), DayOfWeek::fromDate($date)->value);
    }

    public function testItIdentifiesWeekdays(): void
    {
        $this->assertTrue(DayOfWeek::FRIDAY->isWeekday());
        $this->assertFalse(DayOfWeek::SATURDAY->isWeekday());
        $this->assertFalse(DayOfWeek::SUNDAY->isWeekday());
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\TimeSlot;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(TimeSlot::class)]
class TimeSlotTest extends TestCase
{
    public function testItBuildsATimeSlot(): void
    {
        $slot = new TimeSlot('08:30', '12:00');

        $this->assertSame('08:30', $slot->open);
        $this->assertSame('12:00', $slot->close);
        $this->assertSame(210, $slot->getDurationInMinutes());
    }

    #[DataProvider('invalidTimeProvider')]
    public function testItRejectsMalformedTimes(string $open, string $close): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected "HH:MM".');

        new TimeSlot($open, $close);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidTimeProvider(): iterable
    {
        yield 'hour out of range' => ['24:00', '25:00'];
        yield 'minute out of range' => ['08:60', '12:00'];
        yield 'missing padding' => ['8:30', '12:00'];
        yield 'seconds included' => ['08:30:00', '12:00'];
        yield 'invalid close' => ['08:30', 'noon'];
    }

    public function testItRejectsASlotClosingBeforeItOpens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must close after it opens, got "18:00" to "09:00"');

        new TimeSlot('18:00', '09:00');
    }

    public function testItRejectsAZeroLengthSlot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TimeSlot('09:00', '09:00');
    }

    #[DataProvider('containsProvider')]
    public function testItTellsWhetherATimeFallsWithin(string $time, bool $expected): void
    {
        $this->assertSame($expected, (new TimeSlot('08:30', '12:00'))->contains($time));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function containsProvider(): iterable
    {
        yield 'before' => ['08:29', false];
        yield 'opening minute is inclusive' => ['08:30', true];
        yield 'inside' => ['10:00', true];
        yield 'closing minute is exclusive' => ['12:00', false];
        yield 'after' => ['12:01', false];
    }

    public function testItRejectsAMalformedTimeInContains(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TimeSlot('08:30', '12:00'))->contains('8h30');
    }

    public function testItDetectsOverlaps(): void
    {
        $morning = new TimeSlot('08:00', '12:00');

        $this->assertTrue($morning->overlaps(new TimeSlot('11:00', '15:00')));
        $this->assertTrue($morning->overlaps(new TimeSlot('09:00', '10:00')));
        $this->assertFalse($morning->overlaps(new TimeSlot('12:00', '18:00')), 'back-to-back slots do not overlap');
        $this->assertFalse($morning->overlaps(new TimeSlot('14:00', '18:00')));
    }
}

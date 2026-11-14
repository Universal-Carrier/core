<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\BusinessDay;
use UniversalCarrier\Core\Dto\TimeSlot;
use UniversalCarrier\Core\Enum\DayOfWeek;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(BusinessDay::class)]
class BusinessDayTest extends TestCase
{
    public function testItBuildsAnOpenDay(): void
    {
        $day = new BusinessDay(DayOfWeek::MONDAY, [new TimeSlot('08:30', '12:00')]);

        $this->assertSame(DayOfWeek::MONDAY, $day->day);
        $this->assertCount(1, $day->timeSlots);
        $this->assertFalse($day->isClosed());
    }

    public function testItTreatsAnEmptySlotListAsClosed(): void
    {
        $this->assertTrue((new BusinessDay(DayOfWeek::SUNDAY))->isClosed());
        $this->assertTrue(BusinessDay::closed(DayOfWeek::SUNDAY)->isClosed());
    }

    public function testItBuildsASingleSlotDayThroughTheNamedConstructor(): void
    {
        $day = BusinessDay::open(DayOfWeek::TUESDAY, '09:00', '18:00');

        $this->assertSame('09:00', $day->timeSlots[0]->open);
        $this->assertSame(540, $day->getTotalOpenMinutes());
    }

    public function testItSortsSlotsByOpeningTime(): void
    {
        $day = new BusinessDay(DayOfWeek::MONDAY, [
            new TimeSlot('14:00', '19:00'),
            new TimeSlot('08:00', '12:00'),
        ]);

        $this->assertSame(['08:00', '14:00'], array_map(fn (TimeSlot $s): string => $s->open, $day->timeSlots));
    }

    public function testItReindexesSlots(): void
    {
        $day = new BusinessDay(DayOfWeek::MONDAY, [3 => new TimeSlot('08:00', '12:00')]);

        $this->assertSame([0], array_keys($day->timeSlots));
    }

    public function testItRejectsOverlappingSlots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlapping time slots for MONDAY');

        new BusinessDay(DayOfWeek::MONDAY, [
            new TimeSlot('08:00', '13:00'),
            new TimeSlot('12:00', '19:00'),
        ]);
    }

    public function testItRejectsNonTimeSlotItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in timeSlots must be instances of');

        /** @phpstan-ignore argument.type */
        new BusinessDay(DayOfWeek::MONDAY, ['08:00-12:00']);
    }

    public function testItAnswersWhetherItIsOpenAtAGivenTime(): void
    {
        $day = new BusinessDay(DayOfWeek::MONDAY, [
            new TimeSlot('08:00', '12:00'),
            new TimeSlot('14:00', '19:00'),
        ]);

        $this->assertTrue($day->isOpenAt('08:00'));
        $this->assertFalse($day->isOpenAt('13:00'), 'closed over the lunch break');
        $this->assertTrue($day->isOpenAt('18:59'));
        $this->assertFalse($day->isOpenAt('19:00'));
    }

    public function testAClosedDayIsNeverOpen(): void
    {
        $this->assertFalse(BusinessDay::closed(DayOfWeek::SUNDAY)->isOpenAt('12:00'));
        $this->assertSame(0, BusinessDay::closed(DayOfWeek::SUNDAY)->getTotalOpenMinutes());
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\BusinessDay;
use UniversalCarrier\Core\Dto\PickupPoint;
use UniversalCarrier\Core\Dto\TimeSlot;
use UniversalCarrier\Core\Enum\DayOfWeek;
use UniversalCarrier\Core\Enum\PickupPointType;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(PickupPoint::class)]
class PickupPointTest extends TestCase
{
    private function makePickupPoint(mixed ...$overrides): PickupPoint
    {
        /** @phpstan-ignore argument.type */
        return new PickupPoint(...[
            'id' => 'FR-12345',
            'name' => 'Grocery Store Central',
            'type' => PickupPointType::SHOP,
            'address1' => '12 rue de Rivoli',
            'postalCode' => '75004',
            'city' => 'Paris',
            'countryCode' => 'FR',
            ...$overrides,
        ]);
    }

    public function testItBuildsAPickupPointAndNormalizesTheCountryCode(): void
    {
        $point = $this->makePickupPoint(countryCode: ' be ', latitude: 50.85, longitude: 4.35, distanceInKilometer: 0.4);

        $this->assertSame('FR-12345', $point->id);
        $this->assertSame('BE', $point->countryCode);
        $this->assertSame(PickupPointType::SHOP, $point->type);
        $this->assertTrue($point->hasCoordinates());
        $this->assertSame(0.4, $point->distanceInKilometer);
    }

    public function testItRejectsOutOfRangeCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitude must be between -90 and 90');

        $this->makePickupPoint(latitude: 91.0);
    }

    public function testItRejectsANegativeDistance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Distance cannot be negative');

        $this->makePickupPoint(distanceInKilometer: -1.0);
    }

    public function testItRejectsABlankIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('id cannot be blank.');

        $this->makePickupPoint(id: ' ');
    }

    public function testItValidatesOpeningHoursAndHolidaysAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in openingHours must be instances of');

        $this->makePickupPoint(openingHours: ['monday 9-5']);
    }

    public function testItRejectsDuplicateWeekdays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate opening hours declared for MONDAY.');

        $this->makePickupPoint(openingHours: [
            BusinessDay::open(DayOfWeek::MONDAY, '08:00', '12:00'),
            BusinessDay::open(DayOfWeek::MONDAY, '14:00', '19:00'),
        ]);
    }

    public function testItSortsOpeningHoursByWeekday(): void
    {
        $point = $this->makePickupPoint(openingHours: [
            BusinessDay::open(DayOfWeek::WEDNESDAY, '08:00', '12:00'),
            BusinessDay::open(DayOfWeek::MONDAY, '08:00', '12:00'),
        ]);

        $this->assertSame(
            [DayOfWeek::MONDAY, DayOfWeek::WEDNESDAY],
            array_map(fn (BusinessDay $d): DayOfWeek => $d->day, $point->openingHours)
        );
    }

    public function testItLooksUpABusinessDay(): void
    {
        $point = $this->makePickupPoint(openingHours: [BusinessDay::open(DayOfWeek::MONDAY, '08:00', '12:00')]);

        $this->assertNotNull($point->getBusinessDay(DayOfWeek::MONDAY));
        $this->assertNull($point->getBusinessDay(DayOfWeek::TUESDAY));
        $this->assertTrue($point->hasOpeningHours());
    }

    public function testItNormalizesHolidaysToImmutableDates(): void
    {
        $point = $this->makePickupPoint(holidays: [new DateTime('2026-12-25')]);

        $this->assertInstanceOf(DateTimeImmutable::class, $point->holidays[0]);
        $this->assertTrue($point->isHoliday(new DateTimeImmutable('2026-12-25 15:30:00')));
        $this->assertFalse($point->isHoliday(new DateTimeImmutable('2026-12-26')));
    }

    public function testItIsOpenDuringADeclaredSlot(): void
    {
        $point = $this->makePickupPoint(openingHours: [
            new BusinessDay(DayOfWeek::MONDAY, [new TimeSlot('08:00', '12:00'), new TimeSlot('14:00', '19:00')]),
            BusinessDay::closed(DayOfWeek::SUNDAY),
        ]);

        // 2026-09-07 is a Monday, 2026-09-13 a Sunday.
        $this->assertTrue($point->isOpenAt(new DateTimeImmutable('2026-09-07 10:00')));
        $this->assertFalse($point->isOpenAt(new DateTimeImmutable('2026-09-07 13:00')));
        $this->assertFalse($point->isOpenAt(new DateTimeImmutable('2026-09-13 10:00')), 'explicitly closed on Sunday');
    }

    public function testItTrimsEveryFieldAndNullsBlankOptionalOnes(): void
    {
        $point = $this->makePickupPoint(
            id: '  FR-12345  ',
            name: ' Grocery Store Central ',
            address1: '  12 rue de Rivoli ',
            postalCode: ' 75004 ',
            city: ' Paris ',
            state: '   ',
            phone: ' +33123456789 '
        );

        $this->assertSame('FR-12345', $point->id);
        $this->assertSame('Grocery Store Central', $point->name);
        $this->assertSame('12 rue de Rivoli', $point->address1);
        $this->assertSame('75004', $point->postalCode);
        $this->assertSame('Paris', $point->city);
        $this->assertNull($point->state);
        $this->assertSame('+33123456789', $point->phone);
    }

    public function testItValidatesTheTimezone(): void
    {
        $this->assertSame('Europe/Paris', $this->makePickupPoint(timezone: ' Europe/Paris ')->timezone);
        $this->assertTrue($this->makePickupPoint(timezone: 'Europe/Paris')->hasTimezone());
        $this->assertFalse($this->makePickupPoint()->hasTimezone());
    }

    public function testItRejectsAnUnknownTimezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an IANA time zone identifier');

        $this->makePickupPoint(timezone: 'Europe/Atlantis');
    }

    public function testItConvertsAnyMomentIntoTheLocationLocalTime(): void
    {
        $point = $this->makePickupPoint(
            countryCode: 'US',
            openingHours: [BusinessDay::open(DayOfWeek::MONDAY, '09:00', '17:00')],
            timezone: 'America/New_York'
        );

        $utc = new DateTimeZone('UTC');

        // 2026-09-07 is a Monday. New York is UTC-4 that day.
        $this->assertFalse(
            $point->isOpenAt(new DateTimeImmutable('2026-09-07 08:00', $utc)),
            '08:00 UTC is 04:00 in New York'
        );
        $this->assertTrue(
            $point->isOpenAt(new DateTimeImmutable('2026-09-07 15:00', $utc)),
            '15:00 UTC is 11:00 in New York'
        );
    }

    public function testTheConversionCanMoveTheAnswerToAnotherWeekday(): void
    {
        $point = $this->makePickupPoint(
            openingHours: [BusinessDay::open(DayOfWeek::MONDAY, '08:00', '20:00')],
            timezone: 'Pacific/Auckland'
        );

        // 2026-09-06 22:00 UTC is Monday 2026-09-07 10:00 in Auckland.
        $this->assertTrue($point->isOpenAt(new DateTimeImmutable('2026-09-06 22:00', new DateTimeZone('UTC'))));
    }

    public function testAHolidayIsEvaluatedInTheLocationLocalTimeToo(): void
    {
        $point = $this->makePickupPoint(
            openingHours: [BusinessDay::open(DayOfWeek::FRIDAY, '08:00', '20:00')],
            holidays: [new DateTimeImmutable('2026-12-25')],
            timezone: 'Pacific/Auckland'
        );

        // 2026-12-24 22:00 UTC is already Christmas Day in Auckland.
        $this->assertTrue($point->isHoliday(new DateTimeImmutable('2026-12-24 22:00', new DateTimeZone('UTC'))));
        $this->assertFalse($point->isOpenAt(new DateTimeImmutable('2026-12-24 22:00', new DateTimeZone('UTC'))));
    }

    public function testWithoutATimezoneTheMomentIsUsedAsItStands(): void
    {
        $point = $this->makePickupPoint(openingHours: [BusinessDay::open(DayOfWeek::MONDAY, '09:00', '17:00')]);

        $this->assertTrue($point->isOpenAt(new DateTimeImmutable('2026-09-07 10:00', new DateTimeZone('UTC'))));
        $this->assertTrue($point->isOpenAt(new DateTimeImmutable('2026-09-07 10:00', new DateTimeZone('Asia/Tokyo'))));
    }

    public function testItReturnsNullWhenOpeningHoursAreUnknown(): void
    {
        $point = $this->makePickupPoint();

        $this->assertFalse($point->hasOpeningHours());
        $this->assertNull($point->isOpenAt(new DateTimeImmutable('2026-09-07 10:00')));
    }

    public function testItReturnsFalseForAnUndeclaredDayOfAKnownSchedule(): void
    {
        $point = $this->makePickupPoint(openingHours: [BusinessDay::open(DayOfWeek::MONDAY, '08:00', '12:00')]);

        $this->assertFalse($point->isOpenAt(new DateTimeImmutable('2026-09-08 10:00')), 'Tuesday is not declared');
    }

    public function testAHolidayClosesThePointEvenDuringOpeningHours(): void
    {
        $point = $this->makePickupPoint(
            openingHours: [BusinessDay::open(DayOfWeek::FRIDAY, '08:00', '19:00')],
            holidays: [new DateTimeImmutable('2026-12-25')]
        );

        // 2026-12-25 is a Friday.
        $this->assertFalse($point->isOpenAt(new DateTimeImmutable('2026-12-25 10:00')));
    }
}

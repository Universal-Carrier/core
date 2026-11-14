<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Dto\Rate;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(Rate::class)]
class RateTest extends TestCase
{
    public function testItBuildsARate(): void
    {
        $rate = new Rate(
            serviceCode: 'EXPRESS',
            serviceName: 'Express 24h',
            price: new Price(1290),
            requiresPickupPoint: false,
            transitTimeInDays: 1,
            earliestDeliveryDate: new DateTimeImmutable('2026-09-08'),
            latestDeliveryDate: new DateTimeImmutable('2026-09-09'),
            description: 'Delivered before noon',
            metadata: ['guaranteed' => true]
        );

        $this->assertSame('EXPRESS', $rate->serviceCode);
        $this->assertSame(1290, $rate->price->amount);
        $this->assertSame(1, $rate->transitTimeInDays);
        $this->assertSame(['guaranteed' => true], $rate->metadata);
    }

    public function testItDefaultsToAHomeDeliveryWithoutEstimate(): void
    {
        $rate = new Rate('STD', 'Standard', new Price(590));

        $this->assertFalse($rate->requiresPickupPoint);
        $this->assertFalse($rate->hasDeliveryEstimate());
        $this->assertNull($rate->getDeliveryDate());
    }

    public function testItReportsAPickupPointService(): void
    {
        $rate = new Rate('RELAY', 'Pickup point', new Price(390), requiresPickupPoint: true);

        $this->assertTrue($rate->requiresPickupPoint);
    }

    public function testItFallsBackToTheLatestBoundForASingleDeliveryDate(): void
    {
        $rate = new Rate(
            'STD',
            'Standard',
            new Price(590),
            earliestDeliveryDate: new DateTimeImmutable('2026-09-08'),
            latestDeliveryDate: new DateTimeImmutable('2026-09-10')
        );

        $this->assertTrue($rate->hasDeliveryEstimate());
        $this->assertSame('2026-09-10', $rate->getDeliveryDate()?->format('Y-m-d'));
    }

    public function testItUsesTheEarliestBoundWhenNoLatestIsGiven(): void
    {
        $rate = new Rate('STD', 'Standard', new Price(590), earliestDeliveryDate: new DateTimeImmutable('2026-09-08'));

        $this->assertSame('2026-09-08', $rate->getDeliveryDate()?->format('Y-m-d'));
    }

    public function testItRejectsABlankServiceCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('serviceCode cannot be blank.');

        new Rate('  ', 'Standard', new Price(590));
    }

    public function testItRejectsANegativeTransitTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transit time cannot be negative');

        new Rate('STD', 'Standard', new Price(590), transitTimeInDays: -1);
    }

    public function testItRejectsAnInvertedDeliveryWindow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot precede the earliest one');

        new Rate(
            'STD',
            'Standard',
            new Price(590),
            earliestDeliveryDate: new DateTimeImmutable('2026-09-10'),
            latestDeliveryDate: new DateTimeImmutable('2026-09-08')
        );
    }
}

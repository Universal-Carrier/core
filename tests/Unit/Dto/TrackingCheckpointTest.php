<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\TrackingCheckpoint;
use UniversalCarrier\Core\Enum\TrackingStatus;

#[CoversClass(TrackingCheckpoint::class)]
class TrackingCheckpointTest extends TestCase
{
    public function testItKeepsTheTimeZoneOfTheEvent(): void
    {
        $checkpoint = new TrackingCheckpoint(
            TrackingStatus::PICKED_UP,
            new DateTimeImmutable('2026-09-04 11:12', new \DateTimeZone('Europe/Paris'))
        );

        $this->assertSame('2026-09-04T11:12:00+02:00', $checkpoint->at->format(DATE_ATOM));
        $this->assertNull($checkpoint->description);
        $this->assertNull($checkpoint->location);
    }

    public function testItAcceptsAMutableDateWithoutSharingIt(): void
    {
        $date = new DateTime('2026-09-04T09:12:00Z');
        $checkpoint = new TrackingCheckpoint(TrackingStatus::PICKED_UP, $date);

        $date->modify('+1 day');

        $this->assertSame('2026-09-04', $checkpoint->at->format('Y-m-d'));
    }

    public function testItTrimsTheCarrierWording(): void
    {
        $checkpoint = new TrackingCheckpoint(
            TrackingStatus::IN_TRANSIT,
            new DateTimeImmutable('2026-09-05T04:31:00Z'),
            '  Arrivé sur la plateforme  ',
            '   '
        );

        $this->assertSame('Arrivé sur la plateforme', $checkpoint->description);
        $this->assertNull($checkpoint->location);
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Tracking;
use UniversalCarrier\Core\Dto\TrackingCheckpoint;
use UniversalCarrier\Core\Enum\TrackingStatus;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(Tracking::class)]
class TrackingTest extends TestCase
{
    private function checkpoint(TrackingStatus $status, string $at): TrackingCheckpoint
    {
        return new TrackingCheckpoint($status, new DateTimeImmutable($at));
    }

    public function testOnlyTheNumberAndTheStatusAreRequired(): void
    {
        $tracking = new Tracking('XY618254390FR', TrackingStatus::PENDING);

        $this->assertSame('XY618254390FR', $tracking->trackingNumber);
        $this->assertNull($tracking->estimatedDelivery);
        $this->assertNull($tracking->description);
        $this->assertFalse($tracking->hasCheckpoints());
        $this->assertNull($tracking->getLatestCheckpoint());
        $this->assertSame([], $tracking->metadata);
    }

    public function testItSortsTheHistoryOldestFirst(): void
    {
        $tracking = new Tracking('XY618254390FR', TrackingStatus::IN_TRANSIT, checkpoints: [
            $this->checkpoint(TrackingStatus::IN_TRANSIT, '2026-09-05T04:31:00Z'),
            $this->checkpoint(TrackingStatus::PENDING, '2026-09-03T17:00:00Z'),
            $this->checkpoint(TrackingStatus::PICKED_UP, '2026-09-04T09:12:00Z'),
        ]);

        $this->assertSame(
            [TrackingStatus::PENDING, TrackingStatus::PICKED_UP, TrackingStatus::IN_TRANSIT],
            array_map(static fn (TrackingCheckpoint $c): TrackingStatus => $c->status, $tracking->checkpoints)
        );
        $this->assertSame(TrackingStatus::IN_TRANSIT, $tracking->getLatestCheckpoint()?->status);
    }

    public function testItComparesEventsAsInstantsAcrossTimeZones(): void
    {
        // 10:00 in Paris is 08:00 UTC, so it happened before 09:00 UTC.
        $tracking = new Tracking('XY618254390FR', TrackingStatus::IN_TRANSIT, checkpoints: [
            $this->checkpoint(TrackingStatus::IN_TRANSIT, '2026-09-05T09:00:00+00:00'),
            $this->checkpoint(TrackingStatus::PICKED_UP, '2026-09-05T10:00:00+02:00'),
        ]);

        $this->assertSame(TrackingStatus::PICKED_UP, $tracking->checkpoints[0]->status);
    }

    public function testItDelegatesFinalityToTheStatus(): void
    {
        $this->assertTrue((new Tracking('A1', TrackingStatus::DELIVERED))->isFinal());
        $this->assertFalse((new Tracking('A1', TrackingStatus::DELIVERY_ATTEMPTED))->isFinal());
    }

    public function testItTrimsItsStrings(): void
    {
        $tracking = new Tracking(' A1 ', TrackingStatus::IN_TRANSIT, ' En transit ', ' ');

        $this->assertSame('A1', $tracking->trackingNumber);
        $this->assertSame('En transit', $tracking->description);
        $this->assertNull($tracking->location);
    }

    public function testItRejectsABlankTrackingNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Tracking('  ', TrackingStatus::PENDING);
    }

    public function testItValidatesCheckpointsAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in checkpoints must be instances of');

        /** @phpstan-ignore argument.type */
        new Tracking('A1', TrackingStatus::PENDING, checkpoints: ['picked_up']);
    }
}

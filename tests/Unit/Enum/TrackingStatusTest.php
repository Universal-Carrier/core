<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Enum\TrackingStatus;

#[CoversClass(TrackingStatus::class)]
class TrackingStatusTest extends TestCase
{
    public function testOnlyDeliveredAndReturnedAreFinal(): void
    {
        $final = array_values(array_filter(
            TrackingStatus::cases(),
            static fn (TrackingStatus $status): bool => $status->isFinal()
        ));

        $this->assertSame([TrackingStatus::DELIVERED, TrackingStatus::RETURNED], $final);
    }

    public function testItDefinesNineStatuses(): void
    {
        $this->assertSame(
            ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivery_attempted',
                'available_for_pickup', 'delivered', 'exception', 'returned'],
            array_map(static fn (TrackingStatus $status): string => $status->value, TrackingStatus::cases())
        );
    }
}

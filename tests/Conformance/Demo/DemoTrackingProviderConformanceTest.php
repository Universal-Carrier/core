<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use PHPUnit\Framework\Attributes\CoversNothing;
use UniversalCarrier\Conformance\TrackingProviderConformanceTestCase;
use UniversalCarrier\Core\Contract\TrackingProviderInterface;

#[CoversNothing]
class DemoTrackingProviderConformanceTest extends TrackingProviderConformanceTestCase
{
    protected function createCarrier(): TrackingProviderInterface
    {
        return Fixtures::carrier();
    }

    protected function trackedNumber(): string
    {
        return '6D0000000001';
    }

    protected function unscannedNumber(): string
    {
        return '6D9999999999';
    }

    protected function malformedNumber(): string
    {
        return 'XY618254390FR';
    }
}

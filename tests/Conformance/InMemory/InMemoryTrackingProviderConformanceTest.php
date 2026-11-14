<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixture\InMemoryCarrier;
use UniversalCarrier\Conformance\TrackingProviderConformanceTestCase;
use UniversalCarrier\Core\Contract\TrackingProviderInterface;

#[CoversNothing]
class InMemoryTrackingProviderConformanceTest extends TrackingProviderConformanceTestCase
{
    protected function createCarrier(): TrackingProviderInterface
    {
        return new InMemoryCarrier();
    }

    protected function trackedNumber(): string
    {
        return 'SHP-001-01';
    }

    protected function unscannedNumber(): string
    {
        return 'SHP-999-01';
    }

    protected function malformedNumber(): string
    {
        return 'not a tracking number';
    }
}

<?php declare(strict_types=1);

namespace Tests\Conformance\Demo;

use PHPUnit\Framework\Attributes\CoversNothing;
use UniversalCarrier\Conformance\CarrierConformanceTestCase;
use UniversalCarrier\Core\Contract\CarrierInterface;

#[CoversNothing]
class DemoCarrierConformanceTest extends CarrierConformanceTestCase
{
    protected function createCarrier(): CarrierInterface
    {
        return Fixtures::carrier();
    }
}

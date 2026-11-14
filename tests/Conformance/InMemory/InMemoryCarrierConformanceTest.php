<?php declare(strict_types=1);

namespace Tests\Conformance\InMemory;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Fixture\InMemoryCarrier;
use UniversalCarrier\Conformance\CarrierConformanceTestCase;
use UniversalCarrier\Core\Contract\CarrierInterface;

#[CoversNothing]
class InMemoryCarrierConformanceTest extends CarrierConformanceTestCase
{
    protected function createCarrier(): CarrierInterface
    {
        return new InMemoryCarrier();
    }
}

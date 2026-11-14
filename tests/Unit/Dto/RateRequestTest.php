<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Dto\Parcel;
use UniversalCarrier\Core\Dto\RateRequest;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(RateRequest::class)]
class RateRequestTest extends TestCase
{
    private function address(): Address
    {
        return new Address('1 Main St', '75001', 'Paris', 'FR', firstName: 'Jane', lastName: 'Doe');
    }

    public function testItBuildsARateRequest(): void
    {
        $request = new RateRequest(
            sender: $this->address(),
            recipient: $this->address(),
            parcels: [new Parcel(1.2), new Parcel(0.8)],
            shippingDate: new DateTimeImmutable('2026-09-07'),
            serviceCodes: ['EXPRESS']
        );

        $this->assertSame(2, $request->getParcelCount());
        $this->assertEqualsWithDelta(2.0, $request->getTotalWeightInKilogram(), 0.0001);
        $this->assertSame(['EXPRESS'], $request->serviceCodes);
    }

    public function testItRequiresAtLeastOneParcel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A rate request must contain at least one parcel.');

        new RateRequest($this->address(), $this->address(), []);
    }

    public function testItValidatesParcelsAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in parcels must be instances of');

        /** @phpstan-ignore argument.type */
        new RateRequest($this->address(), $this->address(), ['a box']);
    }

    public function testItReindexesParcels(): void
    {
        $request = new RateRequest($this->address(), $this->address(), [5 => new Parcel(1.0)]);

        $this->assertSame([0], array_keys($request->parcels));
    }
}

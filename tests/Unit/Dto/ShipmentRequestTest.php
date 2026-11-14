<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Address;
use UniversalCarrier\Core\Dto\Parcel;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Dto\Rate;
use UniversalCarrier\Core\Dto\RateRequest;
use UniversalCarrier\Core\Dto\ShipmentRequest;
use UniversalCarrier\Core\Enum\ExportReason;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(ShipmentRequest::class)]
class ShipmentRequestTest extends TestCase
{
    private function address(): Address
    {
        return new Address('1 Main St', '75001', 'Paris', 'FR', firstName: 'Jane', lastName: 'Doe');
    }

    public function testItBuildsAShipmentRequest(): void
    {
        $request = new ShipmentRequest(
            serviceCode: 'EXPRESS',
            sender: $this->address(),
            recipient: $this->address(),
            parcels: [new Parcel(1.45)],
            orderReference: 'ORD-1001',
            customerReference: 'CUST-42',
            deliveryInstructions: 'Ring twice',
            metadata: ['cash_on_delivery' => 1990]
        );

        $this->assertSame('EXPRESS', $request->serviceCode);
        $this->assertSame('ORD-1001', $request->orderReference);
        $this->assertSame('CUST-42', $request->customerReference);
        $this->assertSame(1, $request->getParcelCount());
        $this->assertFalse($request->isMultiParcel());
        $this->assertFalse($request->isPickupPointDelivery());
    }

    public function testItNormalizesLanguageCodes(): void
    {
        $request = new ShipmentRequest(
            'STD',
            $this->address(),
            $this->address(),
            [new Parcel(1.0)],
            'ORD-1',
            senderLanguage: ' FR ',
            recipientLanguage: 'NL'
        );

        $this->assertSame('fr', $request->senderLanguage);
        $this->assertSame('nl', $request->recipientLanguage);
    }

    public function testItDefaultsLanguagesToEnglish(): void
    {
        $request = new ShipmentRequest('STD', $this->address(), $this->address(), [new Parcel(1.0)], 'ORD-1');

        $this->assertSame('en', $request->senderLanguage);
        $this->assertSame('en', $request->recipientLanguage);
    }

    public function testItRejectsAnInvalidLanguageCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for senderLanguage: "fra".');

        new ShipmentRequest('STD', $this->address(), $this->address(), [new Parcel(1.0)], 'ORD-1', senderLanguage: 'fra');
    }

    public function testItReportsAMultiParcelShipment(): void
    {
        $request = new ShipmentRequest(
            'STD',
            $this->address(),
            $this->address(),
            [new Parcel(1.2), new Parcel(0.8), new Parcel(2.0)],
            'ORD-1'
        );

        $this->assertTrue($request->isMultiParcel());
        $this->assertSame(3, $request->getParcelCount());
        $this->assertEqualsWithDelta(4.0, $request->getTotalWeightInKilogram(), 0.0001);
    }

    public function testItReportsAPickupPointDelivery(): void
    {
        $request = new ShipmentRequest(
            'RELAY',
            $this->address(),
            $this->address(),
            [new Parcel(1.0)],
            'ORD-1',
            pickupPointId: 'FR-12345'
        );

        $this->assertTrue($request->isPickupPointDelivery());
        $this->assertSame('FR-12345', $request->pickupPointId);
    }

    public function testItDeclaresASaleByDefault(): void
    {
        $request = new ShipmentRequest('STD', $this->address(), $this->address(), [new Parcel(1.0)], 'ORD-1');

        $this->assertSame(ExportReason::SALE_OF_GOODS, $request->exportReason);
    }

    public function testItCarriesAnotherExportReason(): void
    {
        $request = new ShipmentRequest(
            'STD',
            $this->address(),
            $this->address(),
            [new Parcel(1.0)],
            'ORD-1',
            exportReason: ExportReason::GIFT
        );

        $this->assertSame(ExportReason::GIFT, $request->exportReason);
    }

    public function testItRequiresAtLeastOneParcel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A shipment request must contain at least one parcel.');

        new ShipmentRequest('STD', $this->address(), $this->address(), [], 'ORD-1');
    }

    public function testItValidatesParcelsAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in parcels must be instances of');

        /** @phpstan-ignore argument.type */
        new ShipmentRequest('STD', $this->address(), $this->address(), ['a box'], 'ORD-1');
    }

    public function testItRejectsABlankOrderReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('orderReference cannot be blank.');

        new ShipmentRequest('STD', $this->address(), $this->address(), [new Parcel(1.0)], ' ');
    }

    public function testItCarriesTheServiceCodeOverFromAnAcceptedRate(): void
    {
        $rateRequest = new RateRequest(
            sender: $this->address(),
            recipient: $this->address(),
            parcels: [new Parcel(1.45)],
            shippingDate: new DateTimeImmutable('2026-09-07')
        );

        $rate = new Rate('RELAY', 'Pickup point', new Price(390), requiresPickupPoint: true);

        $request = ShipmentRequest::fromRate($rate, $rateRequest, 'ORD-1001', 'FR-12345');

        $this->assertSame('RELAY', $request->serviceCode);
        $this->assertSame('ORD-1001', $request->orderReference);
        $this->assertSame('FR-12345', $request->pickupPointId);
        $this->assertSame($rateRequest->parcels, $request->parcels);
        $this->assertSame('2026-09-07', $request->shippingDate?->format('Y-m-d'));
    }
}

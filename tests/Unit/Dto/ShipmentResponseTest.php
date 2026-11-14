<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use UniversalCarrier\Core\Dto\Label;
use UniversalCarrier\Core\Dto\Price;
use UniversalCarrier\Core\Dto\ShipmentPackage;
use UniversalCarrier\Core\Dto\ShipmentResponse;
use UniversalCarrier\Core\Enum\LabelFormat;
use UniversalCarrier\Core\Enum\LabelSize;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(ShipmentResponse::class)]
class ShipmentResponseTest extends TestCase
{
    public function testItBuildsAMultiParcelResponse(): void
    {
        $response = new ShipmentResponse(
            shipmentId: 'SHP-1',
            packages: [
                new ShipmentPackage('TRK-1', new Label('%PDF-1', LabelFormat::PDF, LabelSize::A6)),
                new ShipmentPackage('TRK-2', new Label('%PDF-2', LabelFormat::PDF, LabelSize::A6)),
            ],
            cost: new Price(1290),
            metadata: ['demo.drop_off_before' => '2026-09-25']
        );

        $this->assertSame('SHP-1', $response->shipmentId);
        $this->assertSame(2, $response->getPackageCount());
        $this->assertTrue($response->isMultiParcel());
        $this->assertSame(['TRK-1', 'TRK-2'], $response->getTrackingNumbers());
        $this->assertCount(2, $response->getLabels());
        $this->assertSame(1290, $response->cost?->amount);
        $this->assertSame(['demo.drop_off_before' => '2026-09-25'], $response->metadata);
    }

    /**
     * Metadata values are `mixed`: a driver may publish a scalar, a list, or the fragment of
     * its carrier's answer it judges worth exposing - a SOAP element included.
     */
    #[DataProvider('metadataProvider')]
    public function testItKeepsMetadataValuesInWhateverShapeTheyCame(mixed $value): void
    {
        $response = new ShipmentResponse('SHP-1', [self::package('TRK-1')], metadata: ['demo.value' => $value]);

        $this->assertSame($value, $response->metadata['demo.value']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function metadataProvider(): iterable
    {
        yield 'scalar' => ['2026-09-25'];
        yield 'list' => [[1, 2]];
        yield 'soap fragment' => [new SimpleXMLElement('<Response><Stat>0</Stat></Response>')];
        yield 'vendor sdk object' => [(object)['stat' => 0]];
    }

    public function testNoMetadataIsPublishedByDefault(): void
    {
        $response = new ShipmentResponse('SHP-1', [self::package('TRK-1')]);

        $this->assertSame([], $response->metadata);
    }

    public function testItKeepsPackagesInRequestOrder(): void
    {
        $response = new ShipmentResponse('SHP-1', [
            self::package('TRK-A'),
            self::package('TRK-B'),
            self::package('TRK-C'),
        ]);

        $this->assertSame(['TRK-A', 'TRK-B', 'TRK-C'], $response->getTrackingNumbers());
        $this->assertSame('TRK-A', $response->getFirstPackage()->trackingNumber);
    }

    public function testItBuildsASingleParcelResponse(): void
    {
        $response = ShipmentResponse::forSingleParcel(
            '6A12345678901',
            new Label('%PDF', LabelFormat::PDF),
            'https://track.example.com/6A12345678901'
        );

        $this->assertSame('6A12345678901', $response->shipmentId);
        $this->assertSame(1, $response->getPackageCount());
        $this->assertFalse($response->isMultiParcel());
        $this->assertSame('https://track.example.com/6A12345678901', $response->getFirstPackage()->trackingUrl);
    }

    /**
     * One label per parcel, always: a package cannot exist without one, so a consumer printing
     * a shipment never has to test for their presence.
     */
    public function testEveryPackageCarriesItsLabel(): void
    {
        $response = new ShipmentResponse('SHP-1', [self::package('TRK-1'), self::package('TRK-2')]);

        $this->assertCount(2, $response->getLabels());
        $this->assertSame('%PDF-TRK-1', $response->getLabels()[0]->content);
    }

    public function testItRequiresAtLeastOnePackage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A shipment response must contain at least one package.');

        new ShipmentResponse('SHP-1', []);
    }

    public function testItValidatesPackagesAtRuntime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in packages must be instances of');

        /** @phpstan-ignore argument.type */
        new ShipmentResponse('SHP-1', ['TRK-1']);
    }

    public function testItRejectsABlankShipmentId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('shipmentId cannot be blank.');

        new ShipmentResponse(' ', [self::package('TRK-1')]);
    }

    private static function package(string $trackingNumber): ShipmentPackage
    {
        return new ShipmentPackage($trackingNumber, new Label('%PDF-' . $trackingNumber, LabelFormat::PDF));
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Label;
use UniversalCarrier\Core\Dto\ShipmentPackage;
use UniversalCarrier\Core\Enum\LabelFormat;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(ShipmentPackage::class)]
class ShipmentPackageTest extends TestCase
{
    public function testItBindsATrackingNumberToALabel(): void
    {
        $package = new ShipmentPackage(
            trackingNumber: '6A12345678901',
            label: new Label('%PDF', LabelFormat::PDF),
            trackingUrl: 'https://track.example.com/6A12345678901',
            parcelReference: 'BOX-1'
        );

        $this->assertSame('6A12345678901', $package->trackingNumber);
        $this->assertSame('%PDF', $package->label->content);
        $this->assertSame('BOX-1', $package->parcelReference);
    }

    public function testItRejectsABlankTrackingNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trackingNumber cannot be blank.');

        new ShipmentPackage('  ', new Label('%PDF', LabelFormat::PDF));
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Enum\LabelSize;

#[CoversClass(LabelSize::class)]
class LabelSizeTest extends TestCase
{
    public function testItExposesTheDimensionsOfStandardSizes(): void
    {
        $this->assertSame(210, LabelSize::A4->getWidthInMillimeters());
        $this->assertSame(297, LabelSize::A4->getHeightInMillimeters());
        $this->assertSame(100, LabelSize::ROLL_100X150->getWidthInMillimeters());
        $this->assertSame(150, LabelSize::ROLL_100X150->getHeightInMillimeters());
    }

    public function testAnUnspecifiedSizeHasNoDimensions(): void
    {
        $this->assertNull(LabelSize::UNSPECIFIED->getWidthInMillimeters());
        $this->assertNull(LabelSize::UNSPECIFIED->getHeightInMillimeters());
    }

    public function testEveryOtherSizeIsFullyDefined(): void
    {
        foreach (LabelSize::cases() as $size) {
            if ($size === LabelSize::UNSPECIFIED) {
                continue;
            }

            $this->assertNotNull($size->getWidthInMillimeters(), $size->value);
            $this->assertNotNull($size->getHeightInMillimeters(), $size->value);
        }
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Enum\LabelFormat;

#[CoversClass(LabelFormat::class)]
class LabelFormatTest extends TestCase
{
    #[DataProvider('binaryProvider')]
    public function testItDistinguishesBinaryFromPrinterLanguagePayloads(LabelFormat $format, bool $isBinary): void
    {
        $this->assertSame($isBinary, $format->isBinary());
    }

    /**
     * @return iterable<string, array{LabelFormat, bool}>
     */
    public static function binaryProvider(): iterable
    {
        yield 'pdf' => [LabelFormat::PDF, true];
        yield 'png' => [LabelFormat::PNG, true];
        yield 'jpeg' => [LabelFormat::JPEG, true];
        yield 'zpl' => [LabelFormat::ZPL, false];
        yield 'epl' => [LabelFormat::EPL, false];
        yield 'dpl' => [LabelFormat::DPL, false];
    }

    public function testEveryCaseHasAFileExtensionAndMediaType(): void
    {
        foreach (LabelFormat::cases() as $format) {
            $this->assertNotSame('', $format->getFileExtension());
            $this->assertStringContainsString('/', $format->getMediaType());
        }
    }

    public function testItMapsJpegToTheConventionalExtension(): void
    {
        $this->assertSame('jpg', LabelFormat::JPEG->getFileExtension());
        $this->assertSame('image/jpeg', LabelFormat::JPEG->getMediaType());
    }

    public function testDocumentFormatsUseTheirIanaRegisteredType(): void
    {
        $this->assertSame('application/pdf', LabelFormat::PDF->getMediaType());
        $this->assertSame('image/png', LabelFormat::PNG->getMediaType());
    }

    /**
     * Thermal printer languages have no IANA registration, so the conventional
     * `application/x-*` names are used: `text/plain` would leave nothing downstream able to
     * tell a ZPL program from a note.
     */
    public function testThermalFormatsUseTheirConventionalVendorType(): void
    {
        $this->assertSame('application/x-zpl', LabelFormat::ZPL->getMediaType());
        $this->assertSame('application/x-epl', LabelFormat::EPL->getMediaType());
        $this->assertSame('application/x-dpl', LabelFormat::DPL->getMediaType());
    }

    public function testNoTwoFormatsShareAMediaType(): void
    {
        $mediaTypes = array_map(static fn (LabelFormat $f): string => $f->getMediaType(), LabelFormat::cases());

        $this->assertSame($mediaTypes, array_unique($mediaTypes));
    }
}

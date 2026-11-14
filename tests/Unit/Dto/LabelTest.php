<?php declare(strict_types=1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniversalCarrier\Core\Dto\Label;
use UniversalCarrier\Core\Enum\LabelFormat;
use UniversalCarrier\Core\Enum\LabelSize;
use UniversalCarrier\Core\Exception\InvalidArgumentException;

#[CoversClass(Label::class)]
class LabelTest extends TestCase
{
    public function testItBuildsALabel(): void
    {
        $label = new Label('%PDF-1.4 ...', LabelFormat::PDF, LabelSize::A6);

        $this->assertSame(LabelFormat::PDF, $label->format);
        $this->assertSame(LabelSize::A6, $label->size);
        $this->assertNull($label->dpi);
        $this->assertSame(12, $label->getSizeInBytes());
    }

    public function testItDefaultsToAnUnspecifiedSize(): void
    {
        $this->assertSame(LabelSize::UNSPECIFIED, (new Label('^XA^XZ', LabelFormat::ZPL))->size);
    }

    public function testItCarriesThePrintResolution(): void
    {
        $this->assertSame(300, (new Label('^XA^XZ', LabelFormat::ZPL, LabelSize::ROLL_100X150, 300))->dpi);
    }

    public function testItRejectsEmptyContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Label content cannot be blank.');

        new Label('   ', LabelFormat::PDF);
    }

    public function testItRejectsANonPositiveDpi(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Label dpi must be strictly positive, got 0.');

        new Label('^XA^XZ', LabelFormat::ZPL, LabelSize::ROLL_100X150, 0);
    }

    public function testItEncodesItsContentForTransport(): void
    {
        $this->assertSame(base64_encode('^XA^XZ'), (new Label('^XA^XZ', LabelFormat::ZPL))->toBase64());
    }

    public function testItSuggestsAFilename(): void
    {
        $this->assertSame('ORD-1001.pdf', (new Label('%PDF', LabelFormat::PDF))->getSuggestedFilename('ORD-1001'));
        $this->assertSame('ORD-1001.zpl', (new Label('^XA', LabelFormat::ZPL))->getSuggestedFilename('ORD-1001'));
    }

    /**
     * The basename is a tracking number in practice, so it comes from a carrier response and
     * is documented as being handed straight to file_put_contents().
     */
    #[DataProvider('unsafeBasenameProvider')]
    public function testItSanitizesTheBasename(string $basename, string $expected): void
    {
        $filename = (new Label('%PDF', LabelFormat::PDF))->getSuggestedFilename($basename);

        $this->assertSame($expected, $filename);
        $this->assertSame($filename, basename($filename), 'the result must never be a path');
        $this->assertStringNotContainsString('..', $filename);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsafeBasenameProvider(): iterable
    {
        yield 'path traversal' => ['../../etc/passwd', 'etc_passwd.pdf'];
        yield 'absolute path' => ['/etc/passwd', 'etc_passwd.pdf'];
        yield 'slashes and spaces' => ['  6A/12 34  ', '6A_12_34.pdf'];
        yield 'null byte' => ["ORD\0-1", 'ORD_-1.pdf'];
        yield 'leading dot' => ['.hidden', 'hidden.pdf'];
        yield 'harmless value untouched' => ['SHP-001-01', 'SHP-001-01.pdf'];
    }

    public function testItRejectsABasenameWithNoUsableCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no usable character');

        (new Label('%PDF', LabelFormat::PDF))->getSuggestedFilename('...');
    }
}

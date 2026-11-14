<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

/**
 * Encoding of a shipping label payload.
 *
 * Format and physical size are two orthogonal axes: a ZPL label exists in several sizes, and
 * a 10x15 label exists in several formats. They are therefore modelled separately, alongside
 * the print resolution which is meaningful for thermal formats.
 *
 * @see LabelSize
 * @see \UniversalCarrier\Core\Dto\Label
 */
enum LabelFormat: string
{

    case PDF = 'pdf';
    case PNG = 'png';
    case JPEG = 'jpeg';

    /** Zebra Programming Language, the de facto standard for thermal label printers. */
    case ZPL = 'zpl';

    /** Eltron Programming Language, used by older Zebra/Eltron thermal printers. */
    case EPL = 'epl';

    /** Datamax Programming Language. */
    case DPL = 'dpl';

    /**
     * Whether the payload is binary data rather than printable text.
     *
     * Binary payloads must never be written to a text stream without care, whereas ZPL, EPL
     * and DPL are plain ASCII and can be piped straight to a printer.
     */
    public function isBinary(): bool
    {
        return match ($this) {
            self::PDF, self::PNG, self::JPEG => true,
            self::ZPL, self::EPL, self::DPL => false,
        };
    }

    /**
     * The conventional file extension for this format, without a leading dot.
     */
    public function getFileExtension(): string
    {
        return match ($this) {
            self::PDF => 'pdf',
            self::PNG => 'png',
            self::JPEG => 'jpg',
            self::ZPL => 'zpl',
            self::EPL => 'epl',
            self::DPL => 'dpl',
        };
    }

    /**
     * The media type for this format.
     *
     * The document and image formats use their IANA-registered type. The three thermal
     * printer languages have no registration, so the `application/x-*` names print spoolers
     * and label vendors use in practice are returned instead: `text/plain` would be
     * technically true and practically useless, since nothing downstream could tell a ZPL
     * program from a note.
     */
    public function getMediaType(): string
    {
        return match ($this) {
            self::PDF => 'application/pdf',
            self::PNG => 'image/png',
            self::JPEG => 'image/jpeg',
            self::ZPL => 'application/x-zpl',
            self::EPL => 'application/x-epl',
            self::DPL => 'application/x-dpl',
        };
    }
}

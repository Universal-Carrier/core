<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

/**
 * Physical dimensions of a shipping label.
 *
 * @see LabelFormat
 * @see \UniversalCarrier\Core\Dto\Label
 */
enum LabelSize: string
{

    case A4 = 'a4';
    case A5 = 'a5';
    case A6 = 'a6';

    /** The 100x150 mm thermal roll label, by far the most common in parcel logistics. */
    case ROLL_100X150 = '100x150';

    /** The 100x200 mm thermal roll label. */
    case ROLL_100X200 = '100x200';

    /**
     * The carrier returned a layout that does not match any standard size, or did not say.
     */
    case UNSPECIFIED = 'unspecified';

    /**
     * Width in millimetres, or null when the size is unspecified.
     */
    public function getWidthInMillimeters(): ?int
    {
        return match ($this) {
            self::A4 => 210,
            self::A5 => 148,
            self::A6 => 105,
            self::ROLL_100X150, self::ROLL_100X200 => 100,
            self::UNSPECIFIED => null,
        };
    }

    /**
     * Height in millimetres, or null when the size is unspecified.
     */
    public function getHeightInMillimeters(): ?int
    {
        return match ($this) {
            self::A4 => 297,
            self::A5 => 210,
            self::A6 => 148,
            self::ROLL_100X150 => 150,
            self::ROLL_100X200 => 200,
            self::UNSPECIFIED => null,
        };
    }
}

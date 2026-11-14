<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

use UniversalCarrier\Core\Enum\ShipmentOption;

/**
 * Default implementation of {@see UnsupportedOptionExceptionInterface}.
 */
class UnsupportedOptionException extends CarrierException implements UnsupportedOptionExceptionInterface
{

    public static function forOption(ShipmentOption $option, string $carrierName): self
    {
        return new self(sprintf('Carrier "%s" does not support the "%s" shipment option.', $carrierName, $option->value));
    }
}

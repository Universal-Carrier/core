<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Default implementation of {@see InvalidArgumentExceptionInterface}.
 *
 * It extends the SPL `\InvalidArgumentException` so that existing `catch` blocks written
 * against the SPL hierarchy keep working, while still being catchable through
 * {@see CarrierExceptionInterface}.
 */
class InvalidArgumentException extends BaseInvalidArgumentException implements InvalidArgumentExceptionInterface {}

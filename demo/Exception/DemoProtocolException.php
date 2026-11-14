<?php declare(strict_types=1);

namespace Demo\Exception;

use RuntimeException;
use UniversalCarrier\Core\Exception\CarrierExceptionInterface;

/**
 * The carrier answered, but not in the shape the driver expects.
 *
 * Neither a rejection of the request nor a network failure, so it implements only the root
 * interface - the "any other error" branch of every contract method.
 */
final class DemoProtocolException extends RuntimeException implements CarrierExceptionInterface {}

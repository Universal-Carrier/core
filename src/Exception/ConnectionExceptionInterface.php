<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Exception;

/**
 * Thrown when communication with the carrier could not be completed.
 *
 * Covers DNS and TCP failures, TLS errors, timeouts, HTTP 5xx responses, malformed payloads
 * returned by the carrier, and authentication failures.
 *
 * Such an error is not tied to the request content: retrying later, or falling back to
 * another carrier, is a reasonable recovery strategy.
 */
interface ConnectionExceptionInterface extends CarrierExceptionInterface {}

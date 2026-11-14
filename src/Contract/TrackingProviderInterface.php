<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Contract;

use UniversalCarrier\Core\Dto\{Tracking, TrackingCheckpoint};
use UniversalCarrier\Core\Enum\TrackingStatus;
use UniversalCarrier\Core\Exception\{CarrierExceptionInterface,
    ConnectionExceptionInterface,
    ShipmentExceptionInterface};

/**
 * A carrier that reports where its parcels stand.
 *
 * @see CarrierInterface for the rules every driver follows.
 */
interface TrackingProviderInterface extends CarrierInterface
{

    /**
     * Reports the current status of a parcel and, when the carrier gives it, its history.
     *
     * Implementations MUST map every carrier event onto a {@see TrackingStatus}, following the
     * rules of the specification (section 5.6) for the ambiguous cases, and MUST keep the
     * carrier's own wording in the description rather than translate or rephrase it. Every
     * {@see TrackingCheckpoint::$at} MUST carry the time zone the event happened in.
     *
     * An event the implementation cannot interpret, and one announcing nothing new such as a
     * notification sent to the recipient, MUST NOT be reported as
     * {@see TrackingStatus::EXCEPTION} nor invent progress: the checkpoint keeps the status of
     * the one before it, or {@see TrackingStatus::PENDING} when it is the first.
     *
     * A well-formed number the carrier does not know yet - a label created but not yet
     * scanned - is not an error: implementations MUST report it as
     * {@see TrackingStatus::PENDING}. A number that cannot belong to this carrier, such as one
     * of the wrong format, MUST throw {@see ShipmentExceptionInterface}.
     *
     * This method has no side effect, and consumers MAY call it as often as the carrier's
     * rate limits allow.
     *
     * @param string $trackingNumber A tracking number issued by this carrier.
     * @return Tracking The parcel's status, for the tracking number asked about.
     * @throws ShipmentExceptionInterface If the number cannot belong to this carrier.
     * @throws ConnectionExceptionInterface If the carrier could not be reached.
     * @throws CarrierExceptionInterface For any other error.
     */
    public function track(string $trackingNumber): Tracking;
}

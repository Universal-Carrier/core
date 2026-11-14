<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

/**
 * Where a parcel stands, normalised across carriers.
 *
 * Carriers report dozens of event codes each; every one of them maps onto one of these cases.
 * The list is kept short on purpose, so that two drivers map the same situation to the same
 * status: the precise wording of an event travels next to it, in
 * {@see \UniversalCarrier\Core\Dto\TrackingCheckpoint::$description}.
 *
 * The rules for the ambiguous cases are part of the specification, section 5.6. Unlike the
 * other enums of this package, this one is closed within a major version: a new status would
 * take events away from existing ones and change what they mean.
 */
enum TrackingStatus: string
{

    /** A label exists, but the carrier does not have the parcel yet. */
    case PENDING = 'pending';

    /** The carrier has taken the parcel: collected, or dropped off by the sender. */
    case PICKED_UP = 'picked_up';

    /** The parcel is moving through the carrier's network, customs clearance included. */
    case IN_TRANSIT = 'in_transit';

    /** The parcel is on its way to the recipient for the final delivery. */
    case OUT_FOR_DELIVERY = 'out_for_delivery';

    /** Delivery was attempted and failed; the carrier will try again or awaits the recipient. */
    case DELIVERY_ATTEMPTED = 'delivery_attempted';

    /** The parcel is waiting for the recipient at a pickup point, post office or locker. */
    case AVAILABLE_FOR_PICKUP = 'available_for_pickup';

    /** The parcel was handed to the recipient, or left where they asked. */
    case DELIVERED = 'delivered';

    /** An incident needs attention: damage, loss, address problem, held in customs. */
    case EXCEPTION = 'exception';

    /** The parcel is going, or has gone, back to the sender. */
    case RETURNED = 'returned';

    /**
     * Whether no further status is expected, so that polling for this parcel can stop.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::DELIVERED, self::RETURNED => true,
            self::PENDING,
            self::PICKED_UP,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERY_ATTEMPTED,
            self::AVAILABLE_FOR_PICKUP,
            self::EXCEPTION => false,
        };
    }
}

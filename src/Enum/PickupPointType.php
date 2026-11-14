<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

/**
 * Nature of an out-of-home delivery location.
 *
 * The distinction matters to the end customer: a locker is reachable at any hour but cannot
 * accept oversized parcels, while a shop has opening hours and a human at the counter.
 */
enum PickupPointType: string
{

    /** A retail business acting as a parcel shop (newsagent, grocery, dry cleaner, ...). */
    case SHOP = 'shop';

    /** An automated parcel locker, usually accessible outside business hours. */
    case LOCKER = 'locker';

    /** A post office counter. */
    case POST_OFFICE = 'post_office';

    /** A depot or agency operated by the carrier itself. */
    case CARRIER_DEPOT = 'carrier_depot';

    /** The carrier exposes a location whose nature cannot be mapped to the cases above. */
    case OTHER = 'other';
}

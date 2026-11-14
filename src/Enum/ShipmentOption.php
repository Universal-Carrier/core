<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

/**
 * Optional parts of a shipment request that a carrier may or may not honour.
 *
 * These are not operations - an operation is an interface of this package - but variations of
 * the data {@see \UniversalCarrier\Core\Contract\ShipmentCreatorInterface::createShipment()}
 * accepts. Whether one is available can depend on the carrier account rather than on the
 * driver alone, which is why it is answered per instance, through
 * {@see \UniversalCarrier\Core\Contract\ShipmentCreatorInterface::supports()}.
 */
enum ShipmentOption: string
{

    /** The carrier can book several parcels as one shipment, one tracking number each. */
    case MULTI_PARCEL = 'multi_parcel';

    /**
     * The carrier accepts customs data (`ParcelItem`) for shipments that require a declaration.
     *
     * Which shipments do is for the driver to determine: it depends on the carrier and on the
     * territories involved, not merely on whether two country codes differ. A driver reporting
     * this MUST forward the declared items when a declaration is required. One that does not
     * MUST reject such a shipment when its parcels declare items, rather than ship it without
     * a customs declaration and have it held at the border.
     */
    case CUSTOMS_DECLARATION = 'customs_declaration';

    /**
     * The carrier accepts a declared insurance value on a parcel.
     *
     * A driver reporting this MUST forward the declared value. One that does not MUST reject
     * a request carrying one, rather than silently ship it uninsured: a consumer that asked
     * for cover and did not get it only finds out once a parcel is lost.
     */
    case INSURANCE = 'insurance';
}

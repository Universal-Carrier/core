<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Enum;

/**
 * Why goods cross a customs border, as declared to customs.
 *
 * The cases follow the categories of the Universal Postal Union's CN22 and CN23 forms, which
 * every carrier's customs declaration maps onto - postal operators use them as is, express
 * carriers under names of their own ("reason for export", "shipment purpose"). The reason
 * matters: customs assess duties on a sale, often exempt a gift below a threshold, and do not
 * tax goods coming back.
 */
enum ExportReason: string
{

    /** Goods sold to the recipient. The usual case for e-commerce. */
    case SALE_OF_GOODS = 'sale_of_goods';

    /** A present between private persons, with no payment involved. */
    case GIFT = 'gift';

    /** A sample of goods, sent free of charge to promote a sale. */
    case COMMERCIAL_SAMPLE = 'commercial_sample';

    /** Papers with no commercial value. */
    case DOCUMENTS = 'documents';

    /** Goods sent back to the party they came from, such as a customer return. */
    case RETURNED_GOODS = 'returned_goods';

    /** Anything else. Carriers usually require a description of the content to go with it. */
    case OTHER = 'other';
}

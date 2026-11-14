<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use DateTimeImmutable;
use UniversalCarrier\Core\Contract\ShipmentDataInterface;
use UniversalCarrier\Core\Enum\ExportReason;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;
use UniversalCarrier\Core\Support\ShipmentDataTrait;

/**
 * Immutable Data Transfer Object carrying everything needed to book a shipment.
 *
 * Unlike {@see RateRequest}, this object is built after a service has been chosen, and adds
 * what booking requires and quoting does not: the service code, the references to print, and
 * the languages to print them in.
 */
final readonly class ShipmentRequest implements ShipmentDataInterface
{

    use ShipmentDataTrait;

    public string $serviceCode;

    /** @var Parcel[] */
    public array $parcels;

    public string $orderReference;

    public ?string $customerReference;

    public ?string $pickupPointId;

    public ?string $deliveryInstructions;

    public string $senderLanguage;

    public string $recipientLanguage;

    /**
     * @param string $serviceCode The carrier service to book, as returned in
     *   {@see Rate::$serviceCode} or agreed out of band with the carrier.
     * @param Address $sender The origin address.
     * @param Address $recipient The destination address.
     * @param Parcel[] $parcels The parcels making up the shipment, at least one.
     * @param string $orderReference The merchant's own order identifier. Printed on the label
     *   and used to reconcile a shipment with an order.
     * @param string|null $customerReference A free-form second reference chosen by the
     *   merchant, printed alongside the order reference when the carrier supports it. Use it
     *   for a customer number, a warehouse code or a return reference - not for a duplicate
     *   of the order reference.
     * @param string|null $pickupPointId The {@see PickupPoint::$id} to deliver to. Mandatory
     *   for out-of-home services, forbidden for home delivery services.
     * @param DateTimeImmutable|null $shippingDate The date the parcels are handed to the
     *   carrier. Null lets the implementation assume the next possible collection.
     * @param string|null $deliveryInstructions A free-form note for the delivery driver.
     *   Carriers truncate it aggressively; keep it short.
     * @param string $senderLanguage The ISO 639-1 code driving the label and document language.
     * @param string $recipientLanguage The ISO 639-1 code driving recipient notifications.
     * @param ExportReason $exportReason Why the goods are sent, declared to customs when the
     *   shipment requires a declaration and ignored otherwise. Defaults to a sale, which is
     *   what an online shop ships.
     * @param array<string, mixed> $metadata Carrier-specific booking options with no standard
     *   equivalent (cash on delivery, saturday delivery, contract number, ...).
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $serviceCode,
        public Address $sender,
        public Address $recipient,
        array $parcels,
        string $orderReference,
        ?string $customerReference = null,
        ?string $pickupPointId = null,
        public ?DateTimeImmutable $shippingDate = null,
        ?string $deliveryInstructions = null,
        string $senderLanguage = 'en',
        string $recipientLanguage = 'en',
        public ExportReason $exportReason = ExportReason::SALE_OF_GOODS,
        public array $metadata = []
    ) {
        Assert::notEmpty($parcels, 'A shipment request must contain at least one parcel.');
        Assert::listOf($parcels, Parcel::class, 'parcels');

        $this->serviceCode = Assert::notBlank($serviceCode, 'serviceCode');
        $this->orderReference = Assert::notBlank($orderReference, 'orderReference');
        $this->customerReference = Assert::trimToNull($customerReference);
        $this->pickupPointId = Assert::trimToNull($pickupPointId);
        $this->deliveryInstructions = Assert::trimToNull($deliveryInstructions);
        $this->parcels = array_values($parcels);
        $this->senderLanguage = Assert::languageCode($senderLanguage, 'senderLanguage');
        $this->recipientLanguage = Assert::languageCode($recipientLanguage, 'recipientLanguage');
    }

    /**
     * Builds a booking request from an accepted rate, carrying its service code over.
     *
     * @throws InvalidArgumentException
     */
    public static function fromRate(
        Rate $rate,
        RateRequest $rateRequest,
        string $orderReference,
        ?string $pickupPointId = null
    ): self {
        return new self(
            serviceCode: $rate->serviceCode,
            sender: $rateRequest->sender,
            recipient: $rateRequest->recipient,
            parcels: $rateRequest->parcels,
            orderReference: $orderReference,
            pickupPointId: $pickupPointId ?? $rateRequest->pickupPointId,
            shippingDate: $rateRequest->shippingDate
        );
    }
}

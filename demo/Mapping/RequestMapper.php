<?php declare(strict_types=1);

namespace Demo\Mapping;

use DateTimeImmutable;
use Demo\Exception\DemoShipmentException;
use Demo\Support\CustomsTerritory;
use UniversalCarrier\Core\Contract\ShipmentDataInterface;
use UniversalCarrier\Core\Dto\{Address, Parcel, ParcelItem, PickupPointQuery, Price, RateRequest, ShipmentRequest};
use UniversalCarrier\Core\Enum\{ExportReason, LabelFormat, PickupPointType};

/**
 * Translates the package's DTOs into the carrier's request payloads.
 *
 * Everything here is pure: no I/O, no state. That is what makes the mapping unit-testable
 * without a network - in a real driver, this class is where most of the tests go.
 */
final readonly class RequestMapper
{

    /** How this carrier names our pickup point types. Depots and "other" do not exist here. */
    public const array RELAY_KINDS = [
        'A' => PickupPointType::SHOP,
        'L' => PickupPointType::LOCKER,
        'P' => PickupPointType::POST_OFFICE,
    ];

    public function __construct(
        private LabelFormat $labelFormat
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function rateRequest(RateRequest $request): array
    {
        return [
            ...$this->movement($request),
            // Service codes are opaque: what the consumer passes is what getRates() returned.
            'products' => $request->serviceCodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shipmentRequest(ShipmentRequest $request): array
    {
        return [
            ...$this->movement($request),
            'parcels' => array_map(
                fn (Parcel $parcel): array => [...$this->parcel($parcel), 'contents' => $this->contents($parcel, $request)],
                $request->parcels
            ),
            'product' => $request->serviceCode,
            'order_ref' => $request->orderReference,
            'ref2' => $request->customerReference,
            'instructions' => $request->deliveryInstructions,
            'label' => [
                'format' => $this->labelFormat === LabelFormat::ZPL ? 'ZPL_203' : 'PDF_10x15',
                'lang' => $this->supportedLanguage($request->senderLanguage),
            ],
            'notify' => [
                'lang' => $this->supportedLanguage($request->recipientLanguage),
                'email' => $request->recipient->email,
                'sms' => $request->recipient->phone,
            ],
            'options' => [
                // A carrier-specific option, passed through the documented escape hatch and
                // namespaced with the carrier code (specification, section 4.2).
                'saturday' => ($request->metadata['demo.saturday_delivery'] ?? false) === true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pickupPointQuery(PickupPointQuery $query): array
    {
        return [
            'country' => $query->countryCode,
            'postal_code' => $query->address?->postalCode,
            'city' => $query->address?->city,
            'lat' => $query->latitude,
            'lng' => $query->longitude,
            'radius_m' => $query->radiusInKilometer === null ? null : (int)round($query->radiusInKilometer * 1000),
            'weight_g' => $query->weightInKilogram === null ? null : (int)round($query->weightInKilogram * 1000),
            'kinds' => $this->relayKinds($query->types),
            'max' => $query->limit,
        ];
    }

    /**
     * The carrier's codes for the requested types. Types it does not have are dropped.
     *
     * @param PickupPointType[] $types
     * @return list<string>
     */
    public function relayKinds(array $types): array
    {
        return array_keys(array_filter(
            self::RELAY_KINDS,
            static fn (PickupPointType $type): bool => in_array($type, $types, true)
        ));
    }

    /**
     * Everything a quote and a booking have in common, written once.
     *
     * This is what ShipmentDataInterface is for: the method accepts a RateRequest and a
     * ShipmentRequest alike, and reads the shared fields and derived accessors off both.
     *
     * @return array<string, mixed>
     */
    private function movement(ShipmentDataInterface $data): array
    {
        return [
            'from' => $this->address($data->sender),
            'to' => $this->address($data->recipient),
            'ship_date' => ($data->shippingDate ?? new DateTimeImmutable('today'))->format('Y-m-d'),
            'relay' => $data->pickupPointId === null ? null : $this->relayReference($data->pickupPointId),
            'parcels' => array_map($this->parcel(...), $data->parcels),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function address(Address $address): array
    {
        return [
            // One name field, as with many carriers: the contact, or else the company.
            'name' => $address->getFullName() ?? $address->getDisplayName(),
            'company' => $address->company,
            'line1' => $address->address1,
            'line2' => $address->address2,
            'line3' => $address->address3,
            'postal_code' => $address->postalCode,
            'city' => $address->city,
            'state' => $address->state,
            'country' => $address->countryCode,
            'phone' => $address->phone,
            'email' => $address->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parcel(Parcel $parcel): array
    {
        return [
            'weight_g' => $parcel->getWeightInGram(),
            'length_mm' => $parcel->getLengthInMillimeter(),
            'width_mm' => $parcel->getWidthInMillimeter(),
            'height_mm' => $parcel->getHeightInMillimeter(),
            'ref' => $parcel->reference,
            'insured_value' => $parcel->insuranceValue === null ? null : $this->money($parcel->insuranceValue),
        ];
    }

    /**
     * The customs declaration of one parcel. Items only matter to customs, so they only
     * travel when this carrier requires a declaration for the route.
     *
     * @return array<string, mixed>|null
     */
    private function contents(Parcel $parcel, ShipmentRequest $request): ?array
    {
        if ($parcel->items === [] || !CustomsTerritory::requiresDeclaration($request->sender, $request->recipient)) {
            return null;
        }

        return [
            'category' => $this->customsCategory($request->exportReason),
            'lines' => array_map($this->customsLine(...), $parcel->items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customsLine(ParcelItem $item): array
    {
        return [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_weight_g' => (int)round($item->unitWeightInKilogram * 1000),
            'unit_value' => $this->money($item->unitPrice),
            'hs_code' => $item->hsTariffCode,
            'origin' => $item->originCountry,
        ];
    }

    /**
     * @return array{amount: string, currency: string}
     */
    private function money(Price $price): array
    {
        return ['amount' => $price->toDecimalString(), 'currency' => $price->currency];
    }

    /**
     * The carrier's codes for the reason of export. It has one for every reason the package
     * knows; a carrier lacking one would have to refuse, never declare another in its place.
     */
    private function customsCategory(ExportReason $reason): string
    {
        return match ($reason) {
            ExportReason::SALE_OF_GOODS => 'SALE',
            ExportReason::GIFT => 'GIFT',
            ExportReason::COMMERCIAL_SAMPLE => 'SAMPLE',
            ExportReason::DOCUMENTS => 'DOCUMENTS',
            ExportReason::RETURNED_GOODS => 'RETURN',
            ExportReason::OTHER => 'OTHER',
        };
    }

    /**
     * Pickup point ids are opaque to the consumer, which lets the driver pack into one string
     * whatever the carrier needs to find the point again - here, a country and a code.
     *
     * @return array{country: string, code: string}
     */
    private function relayReference(string $pickupPointId): array
    {
        if (preg_match('/^([A-Z]{2})-(\d{6})$/', $pickupPointId, $matches) !== 1) {
            throw new DemoShipmentException(sprintf('"%s" is not a Demo Carrier pickup point id.', $pickupPointId));
        }

        return ['country' => $matches[1], 'code' => $matches[2]];
    }

    private function supportedLanguage(string $language): string
    {
        return in_array($language, ['fr', 'en'], true) ? $language : 'en';
    }
}

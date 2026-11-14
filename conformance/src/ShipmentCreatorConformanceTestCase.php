<?php declare(strict_types=1);

namespace UniversalCarrier\Conformance;

use UniversalCarrier\Core\Contract\{PickupPointProviderInterface, RateProviderInterface, ShipmentCreatorInterface};
use UniversalCarrier\Core\Dto\{Label,
    Parcel,
    PickupPointQuery,
    Price,
    Rate,
    RateRequest,
    ShipmentPackage,
    ShipmentRequest,
    ShipmentResponse};
use UniversalCarrier\Core\Enum\{LabelFormat, ShipmentOption};
use UniversalCarrier\Core\Exception\{ShipmentExceptionInterface, UnsupportedOptionExceptionInterface};

/**
 * Checks {@see ShipmentCreatorInterface} against section 5.5 of the specification.
 *
 * Extend it when the driver implements {@see ShipmentCreatorInterface}. These tests book real
 * shipments: point the driver at the carrier's sandbox. Every booking uses an order reference
 * of its own, so that sandboxes rejecting duplicates do not get in the way.
 *
 * When the driver also quotes or lists pickup points, the rules binding those operations to
 * booking are checked too.
 */
abstract class ShipmentCreatorConformanceTestCase extends ConformanceTestCase
{

    /**
     * A fully configured instance of the driver under test.
     */
    abstract protected function createCarrier(): ShipmentCreatorInterface;

    /**
     * A single-parcel, home delivery shipment the carrier can book, with no optional data:
     * no insurance, no items crossing a customs border.
     */
    abstract protected function bookableRequest(): ShipmentRequest;

    /**
     * A shipment to a pickup point the carrier can book. Null, the default, skips the tests
     * about pickup point services.
     */
    protected function pickupPointRequest(): ?ShipmentRequest
    {
        return null;
    }

    /**
     * A shipment that requires a customs declaration by this carrier's rules, with its parcels
     * declaring items. Null, the default, skips the customs test.
     */
    protected function customsRequest(): ?ShipmentRequest
    {
        return null;
    }

    /**
     * The value insured when checking the insurance option.
     */
    protected function insuranceValue(): Price
    {
        return new Price(10000, 'EUR');
    }

    final public function testABookingReturnsOnePackagePerParcel(): void
    {
        $response = $this->book($this->createCarrier(), $this->bookableRequest());

        $this->assertSame(1, $response->getPackageCount(), 'One package per parcel (section 5.5).');
    }

    final public function testEveryParcelComesBackWithItsLabelAsARawPayload(): void
    {
        $response = $this->book($this->createCarrier(), $this->bookableRequest());

        $this->assertCount(
            $response->getPackageCount(),
            $response->getLabels(),
            'Every package carries its label: a booking that cannot produce one throws (section 5.5).'
        );

        foreach ($response->getLabels() as $label) {
            $this->assertLabelIsRaw($label);
        }
    }

    final public function testSupportsAnswersTheSameEveryTime(): void
    {
        $carrier = $this->createCarrier();
        $other = $this->createCarrier();

        foreach (ShipmentOption::cases() as $option) {
            $answer = $this->guarded(static fn (): bool => $carrier->supports($option));

            $this->assertSame($answer, $this->guarded(static fn (): bool => $carrier->supports($option)));
            $this->assertSame(
                $answer,
                $this->guarded(static fn (): bool => $other->supports($option)),
                sprintf('supports(%s) differs between two identically configured instances.', $option->name)
            );
        }
    }

    final public function testMultiParcelBookingAgreesWithSupports(): void
    {
        $carrier = $this->createCarrier();
        $bookable = $this->bookableRequest();
        $parcel = $this->firstParcel($bookable);
        $request = $this->rebuild($bookable, [
            $this->copy($parcel, reference: 'CONFORMANCE-A'),
            $this->copy($parcel, reference: 'CONFORMANCE-B'),
        ]);

        if (!$carrier->supports(ShipmentOption::MULTI_PARCEL)) {
            $this->expectException(UnsupportedOptionExceptionInterface::class);
        }

        $response = $this->book($carrier, $request);

        $this->assertSame(2, $response->getPackageCount(), 'One package per parcel (section 5.5).');

        $references = array_map(static fn (ShipmentPackage $p): ?string => $p->parcelReference, $response->packages);

        if (!in_array(null, $references, true)) {
            $this->assertSame(['CONFORMANCE-A', 'CONFORMANCE-B'], $references, 'Packages MUST follow request order.');
        }
    }

    final public function testInsuranceAgreesWithSupports(): void
    {
        $carrier = $this->createCarrier();
        $bookable = $this->bookableRequest();
        $request = $this->rebuild($bookable, [$this->copy($this->firstParcel($bookable), insurance: $this->insuranceValue())]);

        if (!$carrier->supports(ShipmentOption::INSURANCE)) {
            $this->expectException(UnsupportedOptionExceptionInterface::class);
        }

        $this->assertSame(1, $this->book($carrier, $request)->getPackageCount());
    }

    final public function testCustomsDeclarationAgreesWithSupports(): void
    {
        $carrier = $this->createCarrier();
        $request = $this->customsRequest() ?? self::markTestSkipped('No customsRequest() provided.');

        if (!$carrier->supports(ShipmentOption::CUSTOMS_DECLARATION)) {
            $this->expectException(UnsupportedOptionExceptionInterface::class);
        }

        $this->assertSame($request->getParcelCount(), $this->book($carrier, $request)->getPackageCount());
    }

    final public function testAHomeDeliveryServiceRefusesAPickupPoint(): void
    {
        $bookable = $this->bookableRequest();
        $pickupPointId = $this->pickupPointRequest()->pickupPointId ?? 'CONFORMANCE-0000';

        $this->expectException(ShipmentExceptionInterface::class);

        $this->book($this->createCarrier(), $this->rebuild($bookable, $bookable->parcels, $pickupPointId));
    }

    final public function testAPickupPointServiceRequiresAPickupPoint(): void
    {
        $request = $this->pickupPointRequest() ?? self::markTestSkipped('No pickupPointRequest() provided.');

        $this->expectException(ShipmentExceptionInterface::class);

        $this->book($this->createCarrier(), $this->rebuild($request, $request->parcels, null));
    }

    final public function testAQuotedServiceCanBeBooked(): void
    {
        $carrier = $this->createCarrier();

        if (!$carrier instanceof RateProviderInterface) {
            self::markTestSkipped('The driver does not quote rates.');
        }

        $bookable = $this->bookableRequest();
        $rateRequest = new RateRequest(
            $bookable->sender,
            $bookable->recipient,
            $bookable->parcels,
            $bookable->shippingDate,
            metadata: $bookable->metadata
        );

        $homeDelivery = array_values(array_filter(
            $this->guarded(static fn (): array => $carrier->getRates($rateRequest)),
            static fn (Rate $rate): bool => !$rate->requiresPickupPoint
        ));
        $rate = $homeDelivery[0] ?? self::markTestSkipped('The bookable shipment has no home delivery rate.');

        $response = $this->book($carrier, ShipmentRequest::fromRate($rate, $rateRequest, self::uniqueReference()));

        $this->assertSame(
            $bookable->getParcelCount(),
            $response->getPackageCount(),
            'Every quoted service code MUST be accepted for booking (section 5.3).'
        );
    }

    final public function testAListedPickupPointCanBeBooked(): void
    {
        $carrier = $this->createCarrier();

        if (!$carrier instanceof PickupPointProviderInterface) {
            self::markTestSkipped('The driver does not list pickup points.');
        }

        $request = $this->pickupPointRequest() ?? self::markTestSkipped('No pickupPointRequest() provided.');
        $query = new PickupPointQuery(address: $request->recipient, limit: 1, serviceCode: $request->serviceCode);
        $points = $this->guarded(static fn (): array => $carrier->getPickupPoints($query));
        $point = $points[0] ?? self::markTestSkipped('No pickup point found near the recipient.');

        $response = $this->book($carrier, $this->rebuild($request, $request->parcels, $point->id));

        $this->assertSame(
            $request->getParcelCount(),
            $response->getPackageCount(),
            'Every listed pickup point id MUST be accepted for booking (section 5.4).'
        );
    }

    private function book(ShipmentCreatorInterface $carrier, ShipmentRequest $request): ShipmentResponse
    {
        $unique = $this->rebuild($request, $request->parcels, $request->pickupPointId);

        return $this->guarded(static fn (): ShipmentResponse => $carrier->createShipment($unique));
    }

    /**
     * Label content MUST be the raw payload (section 5.5). A driver forgetting to decode its
     * carrier's base64 is the mistake this catches.
     */
    private function assertLabelIsRaw(Label $label): void
    {
        $this->assertNotSame('', $label->content, 'A label has no content.');

        $looksLike = match ($label->format) {
            LabelFormat::PDF => static fn (string $content): bool => str_starts_with($content, '%PDF'),
            LabelFormat::PNG => static fn (string $content): bool => str_starts_with($content, "\x89PNG"),
            LabelFormat::JPEG => static fn (string $content): bool => str_starts_with($content, "\xFF\xD8\xFF"),
            LabelFormat::ZPL => static fn (string $content): bool => str_contains($content, '^XA'),
            LabelFormat::EPL, LabelFormat::DPL => null,
        };

        if ($looksLike === null) {
            return;
        }

        $decoded = base64_decode($label->content, true);

        $this->assertFalse(
            !$looksLike($label->content) && $decoded !== false && $looksLike($decoded),
            sprintf('The %s label is still base64-encoded: label content MUST be raw bytes.', $label->format->name)
        );
        $this->assertTrue(
            $looksLike($label->content),
            sprintf('The label content does not look like %s.', $label->format->name)
        );
    }

    private function firstParcel(ShipmentRequest $request): Parcel
    {
        return $request->parcels[0] ?? self::fail('A shipment request always has a parcel.');
    }

    private function copy(Parcel $parcel, ?Price $insurance = null, ?string $reference = null): Parcel
    {
        return new Parcel(
            $parcel->weightInKilogram,
            $parcel->length,
            $parcel->width,
            $parcel->height,
            $parcel->items,
            $insurance ?? $parcel->insuranceValue,
            $reference ?? $parcel->reference
        );
    }

    /**
     * @param Parcel[] $parcels
     */
    private function rebuild(ShipmentRequest $request, array $parcels, ?string $pickupPointId = null): ShipmentRequest
    {
        return new ShipmentRequest(
            serviceCode: $request->serviceCode,
            sender: $request->sender,
            recipient: $request->recipient,
            parcels: $parcels,
            orderReference: self::uniqueReference(),
            customerReference: $request->customerReference,
            pickupPointId: $pickupPointId,
            shippingDate: $request->shippingDate,
            deliveryInstructions: $request->deliveryInstructions,
            senderLanguage: $request->senderLanguage,
            recipientLanguage: $request->recipientLanguage,
            exportReason: $request->exportReason,
            metadata: $request->metadata
        );
    }
}

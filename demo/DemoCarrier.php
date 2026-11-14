<?php declare(strict_types=1);

namespace Demo;

use Demo\Api\{DemoApiClient, DemoApiException};
use Demo\Exception\{DemoConnectionException, DemoShipmentException};
use Demo\Mapping\{RequestMapper, ResponseMapper};
use Demo\Support\{CustomsTerritory, Payload};
use UniversalCarrier\Core\Contract\{PickupPointProviderInterface,
    RateProviderInterface,
    ShipmentCreatorInterface,
    TrackingProviderInterface};
use UniversalCarrier\Core\Dto\{PickupPoint, PickupPointQuery, RateRequest, ShipmentRequest, ShipmentResponse, Tracking};
use UniversalCarrier\Core\Enum\{LabelFormat, ShipmentOption, TrackingStatus};
use UniversalCarrier\Core\Exception\{InvalidArgumentException, UnsupportedOptionException};

/**
 * A complete driver for a fictional carrier, written the way a real one would be.
 *
 * Only {@see DemoApiClient} is fake. Everything else - configuration, request mapping,
 * response mapping, error translation, the rules the specification puts on each method - is
 * what a Colissimo, GLS or DHL driver has to do, and in the same places.
 *
 * Each public method follows the same four steps:
 *  1. refuse what the carrier cannot honour, before paying for a network call;
 *  2. map the DTO to the carrier's payload ({@see RequestMapper});
 *  3. send it, translating the transport's exceptions ({@see self::send()});
 *  4. map the answer back to DTOs, enforcing the guarantees the carrier does not give
 *     ({@see ResponseMapper}).
 */
final readonly class DemoCarrier implements
    RateProviderInterface,
    PickupPointProviderInterface,
    ShipmentCreatorInterface,
    TrackingProviderInterface
{

    /** Services that deliver to a pickup point rather than to the recipient's door. */
    private const array PICKUP_POINT_SERVICES = ['RELAY'];

    /**
     * Error codes the carrier uses to say "I cannot carry this". For a quote, that means
     * nothing to offer - an empty answer, not an error (specification, section 5.3).
     */
    private const array NOTHING_TO_OFFER = ['E_WEIGHT', 'E_DESTINATION'];

    private RequestMapper $requests;

    private ResponseMapper $responses;

    /**
     * Everything that varies from one customer account to another is configuration, never
     * something the consumer passes on each call.
     *
     * @param DemoApiClient $api The transport. In a real driver: an HTTP or SOAP client.
     * @param string $accountNumber The customer's contract number with the carrier.
     * @param string $password The API password attached to that contract.
     * @param bool $insuranceContract Whether the contract includes ad valorem insurance. This
     *                                is why supports() answers per instance, not per class.
     * @param LabelFormat $labelFormat What the customer's printers expect.
     */
    public function __construct(
        private DemoApiClient $api,
        private string $accountNumber,
        private string $password,
        private bool $insuranceContract = false,
        LabelFormat $labelFormat = LabelFormat::PDF
    ) {
        if (!in_array($labelFormat, [LabelFormat::PDF, LabelFormat::ZPL], true)) {
            throw new InvalidArgumentException(sprintf(
                'Demo Carrier renders labels as PDF or ZPL, not %s.',
                $labelFormat->name
            ));
        }

        $this->requests = new RequestMapper($labelFormat);
        $this->responses = new ResponseMapper();
    }

    public function getCode(): string
    {
        return 'demo';
    }

    public function getName(): string
    {
        return 'Demo Carrier';
    }

    public function supports(ShipmentOption $option): bool
    {
        return match ($option) {
            ShipmentOption::MULTI_PARCEL, ShipmentOption::CUSTOMS_DECLARATION => true,
            ShipmentOption::INSURANCE => $this->insuranceContract,
        };
    }

    public function getRates(RateRequest $request): array
    {
        try {
            $response = $this->send('/v2/rates', $this->requests->rateRequest($request));
        } catch (DemoShipmentException $e) {
            // The API answers a 60 kg parcel with an error; the contract answers it with no rate.
            if (in_array($e->carrierErrorCode, self::NOTHING_TO_OFFER, true)) {
                return [];
            }

            throw $e;
        }

        return $this->responses->rates($response);
    }

    public function getPickupPoints(PickupPointQuery $query): array
    {
        // Criteria this carrier cannot match are answered locally: no depot exists here, and
        // no home delivery service is served by a pickup point.
        if ($query->types !== [] && $this->requests->relayKinds($query->types) === []) {
            return [];
        }

        if ($query->serviceCode !== null && !in_array($query->serviceCode, self::PICKUP_POINT_SERVICES, true)) {
            return [];
        }

        $points = $this->responses->pickupPoints(
            $this->send('/v2/relays', $this->requests->pickupPointQuery($query))
        );

        // The carrier neither sorts its answer nor honours a maximum; the contract requires both.
        usort(
            $points,
            static fn (PickupPoint $a, PickupPoint $b): int
                => ($a->distanceInKilometer ?? PHP_FLOAT_MAX) <=> ($b->distanceInKilometer ?? PHP_FLOAT_MAX)
        );

        return $query->limit === null ? $points : array_slice($points, 0, $query->limit);
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $this->assertOptionsAreSupported($request);
        $this->assertPickupPointMatchesService($request);

        return $this->responses->shipment(
            $this->send('/v2/shipments', $this->requests->shipmentRequest($request)),
            $request
        );
    }

    public function track(string $trackingNumber): Tracking
    {
        try {
            $response = $this->send('/v2/tracking', ['number' => $trackingNumber]);
        } catch (DemoShipmentException $e) {
            // The carrier answers 404 until the first scan. A well-formed number it does not
            // know yet is a label nobody has handed over: pending, not an error.
            if ($e->carrierErrorCode === 'E_NOT_FOUND') {
                return new Tracking($trackingNumber, TrackingStatus::PENDING);
            }

            throw $e;
        }

        return $this->responses->tracking($response, $trackingNumber);
    }

    /**
     * Refusing is the rule when an option cannot be honoured: silently shipping without the
     * insurance or the customs declaration the consumer asked for is the one thing a driver
     * must never do (specification, section 5.5).
     */
    private function assertOptionsAreSupported(ShipmentRequest $request): void
    {
        $required = [];

        if ($request->isMultiParcel()) {
            $required[] = ShipmentOption::MULTI_PARCEL;
        }

        foreach ($request->parcels as $parcel) {
            if ($parcel->isInsured()) {
                $required[] = ShipmentOption::INSURANCE;
            }

            if ($parcel->items !== [] && CustomsTerritory::requiresDeclaration($request->sender, $request->recipient)) {
                $required[] = ShipmentOption::CUSTOMS_DECLARATION;
            }
        }

        foreach ($required as $option) {
            if (!$this->supports($option)) {
                throw UnsupportedOptionException::forOption($option, $this->getName());
            }
        }
    }

    /**
     * The carrier rejects a relay service without a point, but silently ignores a point sent
     * with a home delivery service - and the parcel would go to the door. The specification
     * requires both mismatches to be refused, so the driver checks rather than trusting the API.
     */
    private function assertPickupPointMatchesService(ShipmentRequest $request): void
    {
        $needsPickupPoint = in_array($request->serviceCode, self::PICKUP_POINT_SERVICES, true);

        if ($needsPickupPoint && !$request->isPickupPointDelivery()) {
            throw new DemoShipmentException(sprintf('Service "%s" requires a pickup point.', $request->serviceCode));
        }

        if (!$needsPickupPoint && $request->isPickupPointDelivery()) {
            throw new DemoShipmentException(sprintf(
                'Service "%s" delivers to the door; choose a pickup point service to use pickup point "%s".',
                $request->serviceCode,
                $request->pickupPointId
            ));
        }
    }

    /**
     * The one place the driver touches the network, and the one place foreign exceptions are
     * translated. Nothing thrown by the transport may reach the consumer as is
     * (specification, section 6).
     *
     * @param array<string, mixed> $body
     */
    private function send(string $endpoint, array $body): Payload
    {
        $body['auth'] = ['account' => $this->accountNumber, 'password' => $this->password];

        try {
            return new Payload($this->api->post($endpoint, $body));
        } catch (DemoApiException $e) {
            throw $this->translate($e);
        }
    }

    /**
     * Sorts carrier errors by retryability, which is what the consumer decides on: a rejected
     * request will fail again, an unreachable or overloaded carrier may not. Authentication
     * failures count as the latter - the request was fine, the configuration was not.
     */
    private function translate(DemoApiException $e): DemoConnectionException|DemoShipmentException
    {
        $retryable = $e->status === 0 || $e->status >= 500 || in_array($e->status, [401, 403, 429], true);

        return $retryable
            ? new DemoConnectionException(sprintf('Demo Carrier is unavailable: %s', $e->getMessage()), $e->status, $e)
            : new DemoShipmentException(sprintf('Demo Carrier rejected the request: %s', $e->getMessage()), $e->errorCode, $e);
    }
}

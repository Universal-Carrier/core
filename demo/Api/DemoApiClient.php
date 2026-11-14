<?php declare(strict_types=1);

namespace Demo\Api;

use DateTimeImmutable;
use DateTimeZone;
use Demo\Exception\DemoProtocolException;
use Demo\Support\{CustomsTerritory, Payload};

/**
 * Stands in for the carrier's servers.
 *
 * In a real driver this class does not exist: its place is taken by an HTTP client (PSR-18,
 * Guzzle, Symfony HttpClient), a `SoapClient`, or the carrier's own SDK. Everything behind
 * `post()` simulates what the carrier would do on its side, so that the driver has something
 * realistic to talk to. None of it is an example of how to write a driver.
 *
 * The conventions of this fictional API are deliberately *not* those of the package, because
 * no real carrier's are either: weights in grams, dimensions in millimetres, amounts as decimal
 * strings, coordinates with a decimal comma, opening hours packed into digit strings, labels
 * in base64, pickup points identified by a (country, code) pair, tracking events with codes of
 * its own, listed newest first and timed in Paris wall-clock time with no offset. Translating
 * all of that is the driver's job.
 *
 * Three tracking numbers have a scripted history, for the demo to show every kind of journey:
 * 6D0000000001 (delivered), 6D0000000002 (failed attempt, then left at a pickup point) and
 * 6D0000000003 (address problem, then returned to sender).
 *
 * Accepted credentials: account "800123", password "demo".
 */
final class DemoApiClient
{

    private const array SERVED_COUNTRIES = ['FR', 'BE', 'LU', 'DE', 'ES', 'IT', 'NL', 'CH', 'US'];

    private const array EU_COUNTRIES = ['FR', 'BE', 'LU', 'DE', 'ES', 'IT', 'NL'];

    private const array RELAY_COUNTRIES = ['FR', 'BE', 'LU'];

    private const array LABEL_FORMATS = ['PDF_10x15', 'ZPL_203'];

    private int $shipmentSequence = 0;

    /** @var array<string, string> Booking time of every label issued, by tracking number. */
    private array $issued = [];

    /**
     * @param int|null $simulatedFailure Make every call fail with this HTTP status, 0 standing
     *                                   for a timeout. Lets the demo show connection errors.
     */
    public function __construct(
        private readonly ?int $simulatedFailure = null
    ) {}

    /**
     * @param array<string, mixed> $body The request, as it would be JSON-encoded on the wire.
     * @return array<string, mixed> The response, as `json_decode(..., true)` would return it.
     * @throws DemoApiException On any non-2xx answer, or when no answer is received.
     */
    public function post(string $endpoint, array $body): array
    {
        if ($this->simulatedFailure !== null) {
            throw new DemoApiException(
                $this->simulatedFailure,
                $this->simulatedFailure === 0 ? 'Operation timed out after 10000 milliseconds' : 'Service Unavailable'
            );
        }

        try {
            $request = new Payload($body);

            $this->authenticate($request->object('auth'));

            return match ($endpoint) {
                '/v2/rates' => $this->rates($request),
                '/v2/relays' => $this->relays($request),
                '/v2/shipments' => $this->shipments($request),
                '/v2/tracking' => $this->tracking($request),
                default => throw new DemoApiException(404, sprintf('No route for "%s".', $endpoint)),
            };
        } catch (DemoProtocolException $e) {
            throw new DemoApiException(400, $e->getMessage(), 'E_SYNTAX');
        }
    }

    private function authenticate(Payload $auth): void
    {
        if ($auth->string('account') !== '800123' || $auth->string('password') !== 'demo') {
            throw new DemoApiException(401, 'Invalid account number or password.', 'E_AUTH');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rates(Payload $request): array
    {
        $from = $this->validAddress($request->object('from'));
        $to = $this->validAddress($request->object('to'));
        $weights = $this->validWeights($request);
        $shipDate = new DateTimeImmutable($request->nullableString('ship_date') ?? 'today');
        $products = $request->strings('products');

        if ($request->has('relay')) {
            $products = ['RELAY'];
        }

        $offers = [];

        // Deliberately not sorted by price: the driver has to do it.
        foreach (['EXPRESS', 'HOME', 'RELAY', 'HOME_SIGNATURE'] as $product) {
            if ($products !== [] && !in_array($product, $products, true)) {
                continue;
            }

            $quote = $this->quote($product, $from, $to, $weights);

            if ($quote === null) {
                continue;
            }

            $offers[] = [
                'product' => $product,
                'label' => match ($product) {
                    'EXPRESS' => 'Demo Express 24h',
                    'HOME' => 'Demo Home',
                    'HOME_SIGNATURE' => 'Demo Home - signature',
                    'RELAY' => 'Demo Relay',
                },
                'price' => ['amount' => number_format($quote['price'], 2, '.', ''), 'currency' => 'EUR'],
                'transit_days' => $quote['days'],
                'delivery' => [
                    'from' => $this->addBusinessDays($shipDate, $quote['days'])->format('Y-m-d'),
                    'to' => $this->addBusinessDays($shipDate, $quote['days'] + 1)->format('Y-m-d'),
                ],
                'relay' => $product === 'RELAY',
                'saturday' => $product === 'EXPRESS',
            ];
        }

        return ['offers' => $offers];
    }

    /**
     * @return array<string, mixed>
     */
    private function relays(Payload $request): array
    {
        if (!$request->has('postal_code') && !($request->has('lat') && $request->has('lng'))) {
            throw new DemoApiException(400, 'Either "postal_code" or "lat" and "lng" are required.', 'E_SEARCH');
        }

        if (!in_array($request->string('country'), self::RELAY_COUNTRIES, true)) {
            return ['relays' => []];
        }

        $kinds = $request->strings('kinds');
        $weight = $request->nullableInt('weight_g');
        $radius = $request->nullableInt('radius_m');

        $relays = array_filter(
            $this->relayDirectory(),
            static fn (array $relay): bool => ($kinds === [] || in_array($relay['kind'], $kinds, true))
                && ($weight === null || $weight <= $relay['max_weight_g'])
                && ($radius === null || $relay['distance_m'] <= $radius)
        );

        // The API ignores any requested maximum and always answers with up to ten points.
        return ['relays' => array_slice(array_values($relays), 0, 10)];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipments(Payload $request): array
    {
        $product = $request->string('product');

        if (!in_array($product, ['EXPRESS', 'HOME', 'HOME_SIGNATURE', 'RELAY'], true)) {
            throw new DemoApiException(422, sprintf('Unknown product "%s".', $product), 'E_PRODUCT');
        }

        $from = $this->validAddress($request->object('from'));
        $to = $this->validAddress($request->object('to'));
        $weights = $this->validWeights($request);
        $quote = $this->quote($product, $from, $to, $weights);

        if ($quote === null) {
            throw new DemoApiException(422, sprintf('Product %s is not available for this shipment.', $product), 'E_PRODUCT');
        }

        if ($product === 'RELAY' && !$request->has('relay')) {
            throw new DemoApiException(422, 'Product RELAY requires a relay point.', 'E_RELAY');
        }

        $parcels = $request->objects('parcels');

        $fromAddress = $request->object('from');
        $toAddress = $request->object('to');
        $customs = CustomsTerritory::of($from, $fromAddress->string('postal_code'))
            !== CustomsTerritory::of($to, $toAddress->string('postal_code'));

        if ($customs) {
            foreach ($parcels as $index => $parcel) {
                if (!$parcel->has('contents') || $parcel->object('contents')->objects('lines') === []) {
                    throw new DemoApiException(422, sprintf('Parcel %d: contents are mandatory for a customs declaration.', $index + 1), 'E_CUSTOMS');
                }
            }
        }

        $labelFormat = $request->object('label')->string('format');

        if (!in_array($labelFormat, self::LABEL_FORMATS, true)) {
            throw new DemoApiException(422, sprintf('Unknown label format "%s".', $labelFormat), 'E_LABEL');
        }

        $shipmentId = sprintf('DMO%08d', ++$this->shipmentSequence);
        $seed = crc32($request->string('order_ref'));
        $booked = [];

        foreach ($parcels as $index => $parcel) {
            $number = sprintf('6D%010d', ($seed + $index) % 10_000_000_000);
            $content = $labelFormat === 'ZPL_203'
                ? '^XA^FO50,50^A0N,40,40^FD' . $number . '^FS^BY3^FO50,120^BCN,120^FD' . $number . '^FS^XZ'
                : "%PDF-1.4\n% Demo Carrier label " . $number . "\n%%EOF\n";

            $booked[] = ['number' => $number, 'label' => base64_encode($content)];
            $this->issued[$number] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s');
        }

        return [
            // "drop_off_before" and, on tracking, "signed_by" are data the contract does not
            // model. A real carrier always has a few: they are what $metadata exists for.
            'shipment' => [
                'id' => $shipmentId,
                'label_format' => $labelFormat,
                'parcels' => $booked,
                'drop_off_before' => (new DateTimeImmutable('+5 days', new DateTimeZone('Europe/Paris')))->format('Y-m-d'),
            ],
            'invoice' => ['amount' => number_format($quote['price'], 2, '.', ''), 'currency' => 'EUR'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tracking(Payload $request): array
    {
        $number = $request->string('number');

        if (preg_match('/^6D\d{10}$/', $number) !== 1) {
            throw new DemoApiException(422, sprintf('"%s" is not a valid parcel number.', $number), 'E_TRACKING_NUMBER');
        }

        $events = $this->trackingHistory($number);

        if ($events === null) {
            throw new DemoApiException(404, sprintf('Parcel %s not found.', $number), 'E_NOT_FOUND');
        }

        $last = $events[0]['code'];

        return [
            'number' => $number,
            'eta' => in_array($last, ['LIV', 'RET', 'RLY'], true) ? null : '2026-09-24',
            'events' => $events,
            'signed_by' => $last === 'LIV' ? 'M. DUPONT' : null,
        ];
    }

    /**
     * @return non-empty-list<array{code: string, label: string, date: string, site: string|null}>|null
     */
    private function trackingHistory(string $number): ?array
    {
        $event = static fn (string $code, string $label, string $date, ?string $site = null): array
            => ['code' => $code, 'label' => $label, 'date' => $date, 'site' => $site];

        $start = [
            $event('CRE', 'Votre colis est prêt à être expédié', '2026-09-21 10:02:00'),
            $event('PCH', 'Votre colis a été pris en charge', '2026-09-21 18:04:00', 'Agence Paris Nord'),
            // A code the carrier added after the driver's table was written - which is how
            // drivers really fall behind, and why an unknown code is not an incident.
            $event('MEL', 'Un email vient de vous être envoyé', '2026-09-21 18:06:00'),
            $event('HUB', 'Votre colis est en cours d\'acheminement', '2026-09-22 02:31:00', 'Plateforme Roissy'),
        ];

        $history = match ($number) {
            '6D0000000001' => [
                ...$start,
                $event('TLV', 'Votre colis est en cours de livraison', '2026-09-22 07:45:00', 'Agence Paris 4e'),
                $event('LIV', 'Votre colis a été livré', '2026-09-22 11:20:00', 'Paris'),
            ],
            '6D0000000002' => [
                ...$start,
                $event('TLV', 'Votre colis est en cours de livraison', '2026-09-22 07:45:00', 'Agence Paris 4e'),
                $event('AVP', 'Destinataire absent, avis de passage déposé', '2026-09-22 12:05:00', 'Paris'),
                $event('RLY', 'Votre colis est disponible dans votre point relais', '2026-09-22 15:30:00', 'Tabac de la Mairie'),
            ],
            '6D0000000003' => [
                ...$start,
                $event('ADR', 'Adresse incomplète, colis en attente', '2026-09-22 09:10:00', 'Agence Paris 4e'),
                $event('RET', 'Votre colis est retourné à l\'expéditeur', '2026-09-23 17:00:00', 'Agence Paris 4e'),
            ],
            default => isset($this->issued[$number])
                ? [$event('CRE', 'Votre colis est prêt à être expédié', $this->issued[$number])]
                : null,
        };

        return $history === null ? null : array_reverse($history);
    }

    /**
     * @param non-empty-list<int> $weights In grams.
     * @return array{price: float, days: int}|null Null when the product cannot carry the shipment.
     */
    private function quote(string $product, string $from, string $to, array $weights): ?array
    {
        $kilograms = array_sum($weights) / 1000;
        $zone = match (true) {
            $from === $to => 0,
            in_array($to, self::EU_COUNTRIES, true) => 1,
            default => 2,
        };

        return match ($product) {
            'HOME' => ['price' => (4.95 + 0.90 * $kilograms) * [1, 2.6, 5.2][$zone], 'days' => [2, 4, 7][$zone]],
            'HOME_SIGNATURE' => $zone === 2 ? null
                : ['price' => (7.05 + 0.90 * $kilograms) * [1, 2.6][$zone], 'days' => [2, 4][$zone]],
            'RELAY' => !in_array($to, self::RELAY_COUNTRIES, true) || max($weights) > 20_000 ? null
                : ['price' => (3.90 + 0.50 * $kilograms) * ($zone === 0 ? 1 : 1.8), 'days' => $zone === 0 ? 3 : 4],
            'EXPRESS' => $zone !== 0 || $from !== 'FR' ? null
                : ['price' => 12.90 + 1.20 * $kilograms, 'days' => 1],
            default => null,
        };
    }

    /**
     * @return string The country code of the address.
     */
    private function validAddress(Payload $address): string
    {
        $country = $address->string('country');

        if (!in_array($country, self::SERVED_COUNTRIES, true)) {
            throw new DemoApiException(422, sprintf('Country %s is not served.', $country), 'E_DESTINATION');
        }

        if ($address->string('postal_code') === '00000') {
            throw new DemoApiException(422, sprintf('Postal code 00000 does not exist in %s.', $country), 'E_POSTCODE');
        }

        return $country;
    }

    /**
     * @return non-empty-list<int>
     */
    private function validWeights(Payload $request): array
    {
        $weights = array_map(static fn (Payload $parcel): int => $parcel->int('weight_g'), $request->objects('parcels'));

        if ($weights === []) {
            throw new DemoApiException(400, 'At least one parcel is required.', 'E_SYNTAX');
        }

        foreach ($weights as $index => $weight) {
            if ($weight > 30_000) {
                throw new DemoApiException(422, sprintf('Parcel %d weighs more than 30 kg.', $index + 1), 'E_WEIGHT');
            }
        }

        return $weights;
    }

    private function addBusinessDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        while ($days > 0) {
            $date = $date->modify('+1 day');

            if ((int)$date->format('N') < 6) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * A handful of points around the Paris city centre, in no particular order.
     *
     * Opening hours are packed per day as four 4-digit times - open, close, open, close - with
     * "0000" for an unused slot. A missing "hours" key means the carrier does not know them.
     *
     * @return list<array{code: string, kind: string, max_weight_g: int, distance_m: int}&array<string, mixed>>
     */
    private function relayDirectory(): array
    {
        $weekdays = ['mon' => '0830120014001930', 'tue' => '0830120014001930', 'wed' => '0830120014001930',
            'thu' => '0830120014001930', 'fri' => '0830120014001930', 'sat' => '0900130000000000',
            'sun' => '0000000000000000'];

        return [
            [
                'code' => '007788', 'country' => 'FR', 'kind' => 'A', 'name' => 'Librairie des Arts',
                'street' => '8 rue de Seine', 'zip' => '75006', 'town' => 'PARIS',
                'lat' => '48,855200', 'lng' => '2,336800', 'distance_m' => 2630, 'max_weight_g' => 20_000,
                'parking' => false,
            ],
            [
                'code' => '004512', 'country' => 'FR', 'kind' => 'A', 'name' => 'Tabac de la Mairie',
                'street' => '12 rue de Rivoli', 'zip' => '75004', 'town' => 'PARIS',
                'lat' => '48,856614', 'lng' => '2,352222', 'distance_m' => 850, 'max_weight_g' => 20_000,
                'parking' => false, 'hours' => $weekdays, 'closures' => ['2026-12-25', '2027-01-01'],
            ],
            [
                'code' => '001203', 'country' => 'FR', 'kind' => 'P', 'name' => 'Bureau de Poste Paris Louvre',
                'street' => '52 rue du Louvre', 'zip' => '75001', 'town' => 'PARIS',
                'lat' => '48,864100', 'lng' => '2,343800', 'distance_m' => 1210, 'max_weight_g' => 30_000,
                'parking' => false,
                'hours' => ['mon' => '0900190000000000', 'tue' => '0900190000000000', 'wed' => '0900190000000000',
                    'thu' => '0900190000000000', 'fri' => '0900190000000000', 'sat' => '0900130000000000',
                    'sun' => '0000000000000000'],
            ],
            [
                'code' => '004987', 'country' => 'FR', 'kind' => 'L', 'name' => 'Consigne Gare de Lyon',
                'street' => 'Place Louis-Armand', 'zip' => '75012', 'town' => 'PARIS',
                'lat' => '48,844300', 'lng' => '2,374300', 'distance_m' => 1940, 'max_weight_g' => 15_000,
                'parking' => true,
                'hours' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], '0600230000000000'),
            ],
        ];
    }
}

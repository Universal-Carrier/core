<?php declare(strict_types=1);

namespace Demo\Support;

use Demo\Exception\DemoProtocolException;

/**
 * A typed reader over a decoded carrier payload.
 *
 * A decoded JSON document is `array<mixed>` all the way down, and PHPStan - rightly - refuses
 * to let `$response['parcels'][0]['tracking']` flow into a `string` parameter. Every real
 * driver ends up needing something like this class: read a key, check its type, and fail with
 * a precise path ("parcels[1].tracking") when the carrier sends something unexpected, instead
 * of a TypeError three calls later.
 */
final readonly class Payload
{

    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private array $data,
        private string $path = '$'
    ) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null;
    }

    public function string(string $key): string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : throw $this->unexpected($key, 'a string', $value);
    }

    public function nullableString(string $key): ?string
    {
        return $this->has($key) ? $this->string($key) : null;
    }

    public function int(string $key): int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : throw $this->unexpected($key, 'an integer', $value);
    }

    public function nullableInt(string $key): ?int
    {
        return $this->has($key) ? $this->int($key) : null;
    }

    public function bool(string $key, bool $default = false): bool
    {
        if (!$this->has($key)) {
            return $default;
        }

        $value = $this->get($key);

        return is_bool($value) ? $value : throw $this->unexpected($key, 'a boolean', $value);
    }

    /**
     * Reads a decimal number sent as a string, tolerating a comma as decimal separator.
     */
    public function decimal(string $key): float
    {
        $value = str_replace(',', '.', $this->string($key));

        return is_numeric($value) ? (float)$value : throw $this->unexpected($key, 'a decimal number', $value);
    }

    public function object(string $key): self
    {
        $value = $this->get($key);

        return is_array($value)
            ? new self($value, $this->path . '.' . $key)
            : throw $this->unexpected($key, 'an object', $value);
    }

    /**
     * @return list<self>
     */
    public function objects(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }

        $value = $this->get($key);

        if (!is_array($value)) {
            throw $this->unexpected($key, 'a list', $value);
        }

        $objects = [];

        foreach (array_values($value) as $index => $item) {
            $objects[] = is_array($item)
                ? new self($item, sprintf('%s.%s[%d]', $this->path, $key, $index))
                : throw $this->unexpected(sprintf('%s[%d]', $key, $index), 'an object', $item);
        }

        return $objects;
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }

        $value = $this->get($key);

        if (!is_array($value)) {
            throw $this->unexpected($key, 'a list', $value);
        }

        $strings = [];

        foreach (array_values($value) as $index => $item) {
            $strings[] = is_string($item)
                ? $item
                : throw $this->unexpected(sprintf('%s[%d]', $key, $index), 'a string', $item);
        }

        return $strings;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    private function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw new DemoProtocolException(sprintf('Missing key "%s.%s".', $this->path, $key));
        }

        return $this->data[$key];
    }

    private function unexpected(string $key, string $expected, mixed $actual): DemoProtocolException
    {
        return new DemoProtocolException(sprintf(
            'Expected "%s.%s" to be %s, got %s.',
            $this->path,
            $key,
            $expected,
            get_debug_type($actual)
        ));
    }
}

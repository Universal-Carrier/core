<?php declare(strict_types=1);

namespace UniversalCarrier\Core\Dto;

use UniversalCarrier\Core\Enum\LabelFormat;
use UniversalCarrier\Core\Enum\LabelSize;
use UniversalCarrier\Core\Exception\InvalidArgumentException;
use UniversalCarrier\Core\Support\Assert;

/**
 * Immutable Data Transfer Object holding a printable shipping label.
 *
 * The content is ALWAYS the raw payload: the bytes of the PDF, or the ASCII of the ZPL
 * program. Implementations MUST decode whatever transport encoding their carrier uses -
 * base64 being the usual one - before building this object. Leaving that ambiguous is how
 * every consumer ends up writing its own "is this base64?" heuristic.
 */
final readonly class Label
{

    /**
     * @param string $content The raw, ready-to-print label payload, never transport-encoded.
     * @param LabelFormat $format The encoding of $content.
     * @param LabelSize $size The physical layout of the label.
     * @param int|null $dpi The print resolution the label was rendered for. Meaningful for
     *   thermal formats, where printing a 203 dpi program on a 300 dpi printer produces a
     *   label at the wrong scale. Null when the carrier did not say.
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $content,
        public LabelFormat $format,
        public LabelSize $size = LabelSize::UNSPECIFIED,
        public ?int $dpi = null
    ) {
        Assert::notBlank($this->content, 'Label content');

        if ($this->dpi !== null && $this->dpi < 1) {
            throw new InvalidArgumentException(sprintf('Label dpi must be strictly positive, got %d.', $this->dpi));
        }
    }

    /**
     * The label payload encoded in base64, for transport over a text channel.
     */
    public function toBase64(): string
    {
        return base64_encode($this->content);
    }

    /**
     * A safe filename for this label, with the extension matching its format.
     *
     * The basename usually comes from a carrier response - a tracking number - so it is
     * sanitised rather than trusted: anything outside `A-Za-z0-9._-` is replaced with an
     * underscore, and leading or trailing dots and underscores are stripped. This guarantees the result is a plain
     * filename and never a path, which matters because the obvious use of this method is to
     * hand it straight to `file_put_contents()`.
     *
     * @throws InvalidArgumentException If $basename holds no usable character.
     */
    public function getSuggestedFilename(string $basename): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($basename)) ?? '';
        $sanitized = trim($sanitized, '._');

        if ($sanitized === '') {
            throw new InvalidArgumentException(sprintf(
                'Cannot build a filename from "%s": no usable character.',
                $basename
            ));
        }

        return sprintf('%s.%s', $sanitized, $this->format->getFileExtension());
    }

    public function getSizeInBytes(): int
    {
        return strlen($this->content);
    }
}

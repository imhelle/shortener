<?php

namespace App\ValueObject;

use Stringable;

/**
 * An absolute http(s) URL that has already passed validation.
 *
 * The type itself is the guarantee: once a Url exists, no caller downstream has
 * to wonder whether the string inside was ever checked, and none of them has to
 * check it again. The sanctioned way to obtain one is UrlNormalizer::normalize().
 */
final readonly class Url implements Stringable
{

    private function __construct(private string $value) {}

    /**
     * @internal Call UrlNormalizer::normalize() instead.
     */
    public static function fromNormalized(string $value): self
    {
        // A cheap invariant rather than a second validation: it exists to catch
        // a caller who found this door and walked in carrying raw input.
        if (1 !== preg_match('~^https?://~', $value)) {
            throw new \LogicException(sprintf('"%s" has not been through UrlNormalizer', $value));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

}

<?php

namespace App\ValueObject;

/**
 * What the shortener hands back: the code it issued, and the URL it actually
 * stored — which may differ from what the user typed.
 *
 * Deliberately not the Link entity: a controller has no business holding a
 * Doctrine-mapped object, whose identity, lazy loading and mutability are all
 * concerns of the persistence layer and of nobody else.
 */
final readonly class ShortenedLink
{

    public function __construct(
        public string $code,
        public Url $url,
    ) {}

}

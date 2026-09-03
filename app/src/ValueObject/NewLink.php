<?php

namespace App\ValueObject;

/**
 * A link on its way into storage: everything a row needs, and nothing else.
 *
 * It exists so the storage port can say what to write without naming the Link
 * entity, which would drag Doctrine's mapping into the domain along with an id
 * that means nothing until the row exists.
 *
 * url arrives as a Url rather than a string, so a value that has been through
 * the normalizer stays recognizable as one all the way down to the insert —
 * and two same-typed arguments can no longer be swapped by accident.
 */
final readonly class NewLink
{

    public function __construct(
        public string $code,
        public Url $url,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {}

}

<?php

namespace App\Service;

use App\ValueObject\ShortenedLink;
use App\ValueObject\Url;

/**
 * What this visitor has already had shortened, so that submitting the same
 * address twice hands back the code issued the first time instead of minting a
 * second one for the same target.
 *
 * The scope is deliberately the visitor and not the table: making one URL map
 * to one code for everybody is a different feature with a different price — it
 * needs a lookup by the long URL, which a 2048-byte column cannot have an index
 * on, and it makes one person's link expiry another person's broken code.
 *
 * Keyed by Url and not by the typed string: "example.com" and
 * "https://example.com/" are one address to the person submitting them, and a
 * memory that disagrees is a memory that misses exactly when it is needed.
 *
 * Forgetting is always allowed. An implementation may hold a handful of entries
 * or none at all, so a null answer means "no promise", never "never happened".
 */
interface LinkMemoryInterface
{
    public function codeFor(Url $url): ?string;

    public function remember(ShortenedLink $link): void;
}

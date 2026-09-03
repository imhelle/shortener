<?php

namespace App\Service;

use App\Entity\Link;
use App\Service\Exception\CodeAlreadyTakenException;

/**
 * The port through which the application reaches stored links. It is declared
 * here, beside the code that calls it, and not next to the Doctrine repository
 * on purpose: the side that needs an operation owns its description, and the
 * infrastructure adopts it. That is what keeps the arrow pointing inward.
 *
 * Storage failures are not part of this contract: they arrive as whatever the
 * driver throws, and the caller decides what an unreachable database means.
 */
interface LinkStorageInterface
{
    /**
     * @throws CodeAlreadyTakenException when another link already holds the code
     */
    public function add(Link $link): void;

    /**
     * @return string|null the stored URL, or null when no link holds the code
     */
    public function findUrlByCode(string $code): ?string;
}

<?php

namespace App\Service;

use App\Service\Exception\LinkShortenerException;

interface LinkShortenerInterface
{
    /**
     * @throws LinkShortenerException
     */
    public function shorten(string $link): string;
}

<?php

namespace App\Service;

use App\Service\Exception\LinkShortenerException;
use App\ValueObject\ShortenedLink;

interface LinkShortenerInterface
{
    /**
     * @throws LinkShortenerException
     */
    public function shorten(string $link): ShortenedLink;
}

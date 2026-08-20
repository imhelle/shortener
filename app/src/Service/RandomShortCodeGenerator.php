<?php

namespace App\Service;

use Random\RandomException;

final readonly class RandomShortCodeGenerator implements ShortCodeGeneratorInterface
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const SHORTENED_LINK_LENGTH = 8;

    public function __construct(private int $length = self::SHORTENED_LINK_LENGTH) {}

    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        $shortenedLink = '';
        for ($symbols = 0; $symbols < $this->length; $symbols++) {
            $maxIndex = strlen(self::ALPHABET) - 1;
            $shortenedLink .= self::ALPHABET[random_int(0, $maxIndex)];
        }
        return $shortenedLink;
    }

}

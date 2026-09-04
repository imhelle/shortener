<?php

namespace App\Service;

use Random\RandomException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RandomShortCodeGenerator implements ShortCodeGeneratorInterface
{
    private const SHORTENED_LINK_LENGTH = 8;

    /**
     * The length is a constructor argument and the constant is only its default:
     * the column decides how long a code may be, this class decides how long the
     * next one is, and those are different pieces of knowledge.
     */
    public function __construct(
        private int $length = self::SHORTENED_LINK_LENGTH,
        #[Autowire(param: 'app.short_code_mode')]
        private ShortCodeMode $mode = ShortCodeMode::Strict,
    ) {}

    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        $alphabet = $this->mode->alphabet();
        $maxIndex = strlen($alphabet) - 1;

        $shortenedLink = '';
        for ($symbols = 0; $symbols < $this->length; $symbols++) {
            $shortenedLink .= $alphabet[random_int(0, $maxIndex)];
        }
        return $shortenedLink;
    }

}

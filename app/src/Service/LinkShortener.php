<?php

namespace App\Service;

use App\Entity\Link;
use App\Repository\LinkRepository;
use App\Service\Exception\CodeAlreadyTakenException;
use App\Service\Exception\LinkShortenerException;
use App\ValueObject\ShortenedLink;
use Psr\Log\LoggerInterface;

readonly class LinkShortener implements LinkShortenerInterface
{

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private LinkRepository $linkRepository,
        private ShortCodeGeneratorInterface $shortCodeGenerator,
        private UrlNormalizer $urlNormalizer,
        private LoggerInterface $logger,
    ) {}

    public function shorten(string $link): ShortenedLink
    {
        // The single gate for every entry point: console, form and later the API.
        // Throws InvalidUrlException, which the caller already handles as a
        // LinkShortenerException, before a single code is drawn.
        $url = $this->urlNormalizer->normalize($link);
        $lastCollision = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $shortenedLink = $this->shortCodeGenerator->generate();
            try {
                $linkEntity = new Link($url->toString(), $shortenedLink);
                $this->linkRepository->add($linkEntity);

                return new ShortenedLink($shortenedLink, $url);
            } catch (CodeAlreadyTakenException $e) {
                // Expected: someone already holds this code, another draw may win.
                $lastCollision = $e;
                $this->logger->warning('Short codes collision', [
                    'code' => $shortenedLink,
                    'attempt' => $attempt,
                ]);
            } catch (\Throwable $e) {
                // Unexpected: a retry would hit the same wall, so give up now.
                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                    'code' => $shortenedLink,
                    'attempt' => $attempt,
                ]);

                throw new LinkShortenerException('Failed to shorten link', previous: $e);
            }
        }

        // Reachable only when every attempt collided, so $lastCollision is set.
        // Logged at error level on purpose: in prod the fingers_crossed handler
        // flushes its buffer at error, which is what surfaces the warnings above.
        $this->logger->error('Ran out of attempts to find a free short code', [
            'exception' => $lastCollision,
            'attempts' => self::MAX_ATTEMPTS,
        ]);

        throw new LinkShortenerException(
            sprintf('Failed to shorten link after %d attempts', self::MAX_ATTEMPTS),
            previous: $lastCollision,
        );
    }

}

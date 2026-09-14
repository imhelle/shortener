<?php

namespace App\Service;

use App\ValueObject\ShortenedLink;

/**
 * Answers a repeated submission with the code already issued for that address,
 * instead of drawing a second one and writing a second row.
 *
 * It is a decorator and not a branch inside LinkShortener because the two
 * answer different questions: one knows how to mint a code and survive a
 * collision, this one knows what the caller has been given before. Keeping them
 * apart is also what keeps the session out of the shortener — the memory
 * arrives as a port, and every test here runs without an HTTP request.
 *
 * The controller is untouched by all of this: it asks for
 * LinkShortenerInterface, exactly as before, and services.yaml decides which
 * implementation that is.
 */
final readonly class RememberingLinkShortener implements LinkShortenerInterface
{

    public function __construct(
        private LinkShortenerInterface $linkShortener,
        private LinkMemoryInterface $linkMemory,
        private UrlNormalizer $urlNormalizer,
    ) {}

    public function shorten(string $link): ShortenedLink
    {
        // The same gate the shortener itself runs, called a second time rather
        // than a second opinion about the input: normalize() is pure and
        // idempotent, and its result is the only fair key for the memory. Going
        // by the typed string instead would treat "example.com" and
        // "https://example.com/" as two different links, which is precisely the
        // case this class exists to catch.
        //
        // Invalid input throws here, before the memory is touched, exactly as it
        // did before this class existed.
        $url = $this->urlNormalizer->normalize($link);
        $code = $this->linkMemory->codeFor($url);

        if (null !== $code) {
            return new ShortenedLink($code, $url);
        }

        // The raw input goes on to the shortener, not the normalized URL: this
        // class is not in the business of deciding what the shortener receives.
        $shortenedLink = $this->linkShortener->shorten($link);
        $this->linkMemory->remember($shortenedLink);

        return $shortenedLink;
    }

}

<?php

namespace App\Http;

use App\Service\LinkMemoryInterface;
use App\ValueObject\ShortenedLink;
use App\ValueObject\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The visitor's memory, kept in the session — which is why this class lives
 * here and not beside the interface it implements: a session exists only inside
 * an HTTP request, and the service asking the question must not learn that.
 *
 * Outside a request there is no memory at all. The console command reaches the
 * shortener through the same decorator, and there every lookup misses and every
 * write is dropped — silently and on purpose, because "the same link twice"
 * means nothing between two separate invocations of a CLI command.
 */
final readonly class SessionLinkMemory implements LinkMemoryInterface
{

    private const KEY = 'shortened_links';

    /**
     * Enough to cover someone working through a page of links, and capped
     * because the whole session is read back and unserialized on every request
     * that touches it: this array is paid for by all of them, not only by the
     * one that grew it. Twenty entries at the 2048-byte URL that UrlNormalizer
     * allows come to some 40 KB of worst case; ordinary URLs make it a
     * hundredth of that. The oldest entries fall off the front, so the recent
     * ones — the ones likely to be submitted again — are the ones kept.
     */
    private const CAPACITY = 20;

    public function __construct(private RequestStack $requestStack) {}

    public function codeFor(Url $url): ?string
    {
        $code = $this->read($this->session())[$url->toString()] ?? null;

        // Anything else means the session was written by another version of this
        // code, or by hand. Not our data, so not our answer.
        return is_string($code) ? $code : null;
    }

    public function remember(ShortenedLink $link): void
    {
        $session = $this->session();

        if (null === $session) {
            return;
        }

        $links = $this->read($session);

        // Unset before set: PHP keeps insertion order, so re-adding an existing
        // key would leave it in its old place and let a link that is submitted
        // over and over be pushed out by newer ones.
        unset($links[$link->url->toString()]);
        $links[$link->url->toString()] = $link->code;

        $session->set(self::KEY, array_slice($links, -self::CAPACITY));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(?SessionInterface $session): array
    {
        $links = $session?->get(self::KEY);

        return is_array($links) ? $links : [];
    }

    /**
     * Null outside a request, and inside one only when a session was actually
     * attached — Request::getSession() throws otherwise, and a shortener has no
     * business failing because it was called from a command line.
     */
    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }

}

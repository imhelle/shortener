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
     * Enough to cover a person working through a page of links, small enough
     * that nobody can grow the session by submitting in a loop. The oldest
     * entries fall off the front, so the recent ones — the ones likely to be
     * submitted again — are the ones kept.
     */
    private const CAPACITY = 20;

    public function __construct(private RequestStack $requestStack) {}

    public function codeFor(Url $url): ?string
    {
        $code = $this->read()[$url->toString()] ?? null;

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

        $links = $this->read();

        // Unset before set: PHP keeps insertion order, so re-adding an existing
        // key would leave it in its old place and let a link that is submitted
        // over and over be pushed out by newer ones.
        unset($links[$link->url->toString()]);
        $links[$link->url->toString()] = $link->code;

        $session->set(self::KEY, array_slice($links, -self::CAPACITY, preserve_keys: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $links = $this->session()?->get(self::KEY);

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

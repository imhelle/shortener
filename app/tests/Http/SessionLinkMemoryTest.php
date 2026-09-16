<?php

namespace App\Tests\Http;

use App\Http\SessionLinkMemory;
use App\Service\UrlNormalizer;
use App\ValueObject\ShortenedLink;
use App\ValueObject\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(SessionLinkMemory::class)]
final class SessionLinkMemoryTest extends TestCase
{

    /**
     * A real Session over in-memory storage, reached through a RequestStack the
     * way the framework would fill it. No HTTP, no kernel: what is under test is
     * the bookkeeping inside the session, not the way the session gets there.
     */
    private function memory(): SessionLinkMemory
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $stack = new RequestStack();
        $stack->push($request);

        return new SessionLinkMemory($stack);
    }

    /** Link number $n: a distinct address with a distinct, 8-character code. */
    private function link(int $n): ShortenedLink
    {
        return new ShortenedLink(sprintf('%08d', $n), $this->url($n));
    }

    private function url(int $n): Url
    {
        return (new UrlNormalizer())->normalize(sprintf('example.com/%d', $n));
    }

    #[Test]
    public function remembersTheCodeItWasGiven(): void
    {
        $memory = $this->memory();

        self::assertNull($memory->codeFor($this->url(1)));

        $memory->remember($this->link(1));

        self::assertSame($this->link(1)->code, $memory->codeFor($this->url(1)));
    }

    #[Test]
    public function forgetsTheOldestEntryOnceItIsFull(): void
    {
        $memory = $this->memory();

        for ($n = 1; $n <= SessionLinkMemory::CAPACITY + 1; $n++) {
            $memory->remember($this->link($n));
        }

        self::assertNull($memory->codeFor($this->url(1)), 'the first entry should have fallen off the front');
        self::assertSame($this->link(2)->code, $memory->codeFor($this->url(2)), 'the second oldest should have stayed');
        self::assertSame(
            $this->link(SessionLinkMemory::CAPACITY + 1)->code,
            $memory->codeFor($this->url(SessionLinkMemory::CAPACITY + 1)),
            'the entry that caused the eviction should be there',
        );
    }

    #[Test]
    public function submittingALinkAgainSavesItFromEviction(): void
    {
        $memory = $this->memory();

        for ($n = 1; $n <= SessionLinkMemory::CAPACITY; $n++) {
            $memory->remember($this->link($n));
        }

        // The same address once more — the case this whole feature exists for.
        // It has to move to the back of the queue, or a link submitted over and
        // over would be pushed out by links submitted once.
        $memory->remember($this->link(1));
        $memory->remember($this->link(SessionLinkMemory::CAPACITY + 1));

        self::assertSame($this->link(1)->code, $memory->codeFor($this->url(1)), 'the resubmitted entry should have survived');
        self::assertNull($memory->codeFor($this->url(2)), 'the next oldest should have been dropped instead');
    }

}

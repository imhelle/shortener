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
        return $this->memoryOver(new Session(new MockArraySessionStorage()));
    }

    /** The same, over a session the test has already written to by hand. */
    private function memoryOver(Session $session): SessionLinkMemory
    {
        $request = new Request();
        $request->setSession($session);

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
    public function ignoresAnEntryThatIsNotACode(): void
    {
        $session = new Session(new MockArraySessionStorage());
        // Written the way a previous version of this class, or a hand, might
        // have left it. The sound entry beside it is not decoration: without it
        // a wrong key here would empty the memory, and this test would pass on
        // a class that never looked at the session at all.
        $session->set('shortened_links', [
            'https://example.com/1' => '00000001',
            'https://example.com/2' => ['nothing', 'like', 'a', 'code'],
        ]);

        $memory = $this->memoryOver($session);

        self::assertSame('00000001', $memory->codeFor($this->url(1)), 'the sound entry should still be readable');
        self::assertNull($memory->codeFor($this->url(2)), 'a value that is not a string is not an answer');
    }

    #[Test]
    public function ignoresASessionValueThatIsNotAnArray(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('shortened_links', 'not an array at all');

        $memory = $this->memoryOver($session);

        self::assertNull($memory->codeFor($this->url(1)));

        // And it recovers: the next write replaces the nonsense rather than
        // trying to add to it.
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

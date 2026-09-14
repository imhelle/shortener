<?php

namespace App\Tests\Service;

use App\Service\Exception\InvalidUrlException;
use App\Service\LinkMemoryInterface;
use App\Service\LinkShortenerInterface;
use App\Service\RememberingLinkShortener;
use App\Service\UrlNormalizer;
use App\ValueObject\ShortenedLink;
use App\ValueObject\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RememberingLinkShortener::class)]
final class RememberingLinkShortenerTest extends TestCase
{

    /** A memory with no session under it, so these tests need no request. */
    private function memory(): LinkMemoryInterface
    {
        return new class implements LinkMemoryInterface {
            /** @var array<string, string> */
            private array $codes = [];

            public function codeFor(Url $url): ?string
            {
                return $this->codes[$url->toString()] ?? null;
            }

            public function remember(ShortenedLink $link): void
            {
                $this->codes[$link->url->toString()] = $link->code;
            }
        };
    }

    /** Issues a fresh code per call, so a reused one cannot pass unnoticed. */
    private function shortenerIssuing(string ...$codes): LinkShortenerInterface
    {
        return new class($codes) implements LinkShortenerInterface {
            public function __construct(private array $codes) {}

            public function shorten(string $link): ShortenedLink
            {
                $code = array_shift($this->codes)
                    ?? throw new \LogicException('the shortener was called more often than the test expected');

                return new ShortenedLink($code, (new UrlNormalizer())->normalize($link));
            }
        };
    }

    #[Test]
    public function shortensAnAddressItHasNotSeen(): void
    {
        $shortener = new RememberingLinkShortener(
            $this->shortenerIssuing('aaaaaaaa'), $this->memory(), new UrlNormalizer(),
        );

        self::assertSame('aaaaaaaa', $shortener->shorten('example.com')->code);
    }

    #[Test]
    public function answersASecondSubmissionWithTheSameCode(): void
    {
        // One code only: reaching the inner shortener twice throws inside the
        // double, which is the failure this test is really watching for.
        $shortener = new RememberingLinkShortener(
            $this->shortenerIssuing('aaaaaaaa'), $this->memory(), new UrlNormalizer(),
        );

        $first = $shortener->shorten('example.com');
        $second = $shortener->shorten('example.com');

        self::assertSame($first->code, $second->code);
        self::assertSame($first->url->toString(), $second->url->toString());
    }

    #[Test]
    public function recognizesTheSameAddressWrittenDifferently(): void
    {
        $shortener = new RememberingLinkShortener(
            $this->shortenerIssuing('aaaaaaaa'), $this->memory(), new UrlNormalizer(),
        );

        // The reason the memory is keyed by the normalized Url: to the person
        // typing these, they are one address.
        $first = $shortener->shorten('example.com');
        $second = $shortener->shorten('  HTTPS://Example.com  ');

        self::assertSame($first->code, $second->code);
    }

    #[Test]
    public function keepsDifferentAddressesApart(): void
    {
        $shortener = new RememberingLinkShortener(
            $this->shortenerIssuing('aaaaaaaa', 'bbbbbbbb'), $this->memory(), new UrlNormalizer(),
        );

        self::assertNotSame(
            $shortener->shorten('example.com/one')->code,
            $shortener->shorten('example.com/two')->code,
        );
    }

    #[Test]
    public function refusesInvalidInputBeforeReachingTheMemory(): void
    {
        $memory = $this->createMock(LinkMemoryInterface::class);
        $memory->expects(self::never())->method('codeFor');
        $memory->expects(self::never())->method('remember');

        $shortener = new RememberingLinkShortener(
            $this->shortenerIssuing(), $memory, new UrlNormalizer(),
        );

        $this->expectException(InvalidUrlException::class);
        $shortener->shorten('javascript:alert(1)');
    }

    #[Test]
    public function remembersNothingWhenShorteningFails(): void
    {
        $memory = $this->createMock(LinkMemoryInterface::class);
        $memory->expects(self::never())->method('remember');

        $failing = $this->createStub(LinkShortenerInterface::class);
        $failing->method('shorten')->willThrowException(new \RuntimeException('storage is down'));

        $shortener = new RememberingLinkShortener($failing, $memory, new UrlNormalizer());

        $this->expectException(\RuntimeException::class);
        $shortener->shorten('example.com');
    }

}

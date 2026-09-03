<?php

namespace App\Tests\Service;

use App\Service\LinkStorageInterface;
use App\Service\Exception\CodeAlreadyTakenException;
use App\Service\Exception\InvalidUrlException;
use App\Service\Exception\LinkShortenerException;
use App\Service\LinkShortener;
use App\Service\ShortCodeGeneratorInterface;
use App\Service\UrlNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(LinkShortener::class)]
final class LinkShortenerTest extends TestCase
{
    // Hands out known codes in order, so assertions can name the expected one.
    private function generatorReturning(string ...$codes): ShortCodeGeneratorInterface
    {
        return new class($codes) implements ShortCodeGeneratorInterface {
            public function __construct(private array $codes) {}

            public function generate(): string
            {
                // Not an assertion: a double running dry means the test itself is wrong.
                return array_shift($this->codes)
                    ?? throw new \LogicException('generator ran out of codes');
            }
        };
    }

    #[Test]
    public function returnsGeneratedCode(): void
    {
        $storage = $this->createMock(LinkStorageInterface::class);
        $storage->expects(self::once())->method('add');

        $shortener = new LinkShortener($storage, $this->generatorReturning('aaaaaaaa'), new UrlNormalizer(), new NullLogger());

        self::assertSame('aaaaaaaa', $shortener->shorten('https://example.com')->code);
    }

    #[Test]
    public function retriesWithANewCodeAfterCollision(): void
    {
        $calls = 0;
        // A stub, not a mock: the call count is asserted below by hand.
        $storage = $this->createStub(LinkStorageInterface::class);
        $storage->method('add')->willReturnCallback(function () use (&$calls): void {
            if (++$calls === 1) {
                throw new CodeAlreadyTakenException('aaaaaaaa');
            }
        });

        $shortener = new LinkShortener(
            $storage, $this->generatorReturning('aaaaaaaa', 'bbbbbbbb'), new UrlNormalizer(), new NullLogger(),
        );

        self::assertSame('bbbbbbbb', $shortener->shorten('https://example.com')->code);
        self::assertSame(2, $calls);
    }

    #[Test]
    public function doesNotRetryWhenStorageIsBroken(): void
    {
        $storage = $this->createMock(LinkStorageInterface::class);
        // The point: one attempt, not three — retrying a dead database is pointless.
        $storage
            ->expects(self::once())->method('add')
            ->willThrowException(new \RuntimeException('server has gone away'));

        $shortener = new LinkShortener($storage, $this->generatorReturning('aaaaaaaa'), new UrlNormalizer(), new NullLogger());

        $this->expectException(LinkShortenerException::class);
        $shortener->shorten('https://example.com');
    }

    #[Test]
    public function rejectsAnUnacceptableUrlBeforeTouchingStorage(): void
    {
        $storage = $this->createMock(LinkStorageInterface::class);
        // The gate stands in front of everything: no code drawn, no row written.
        $storage->expects(self::never())->method('add');

        $shortener = new LinkShortener(
            $storage, $this->generatorReturning(), new UrlNormalizer(), new NullLogger(),
        );

        $this->expectException(InvalidUrlException::class);
        $shortener->shorten('javascript:alert(1)');
    }

    #[Test]
    public function reportsTheStoredUrlRatherThanTheInput(): void
    {
        $storage = $this->createStub(LinkStorageInterface::class);

        $shortener = new LinkShortener(
            $storage, $this->generatorReturning('aaaaaaaa'), new UrlNormalizer(), new NullLogger(),
        );

        // The caller must be able to show what was actually saved: "google.com"
        // went in, "https://google.com" is what the code now points at.
        self::assertSame('https://google.com', $shortener->shorten('google.com')->url->toString());
    }
}

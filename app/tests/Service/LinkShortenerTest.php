<?php

namespace App\Tests\Service;

use App\Repository\LinkRepository;
use App\Service\Exception\CodeAlreadyTakenException;
use App\Service\Exception\LinkShortenerException;
use App\Service\LinkShortener;
use App\Service\ShortCodeGeneratorInterface;
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
        $repository = $this->createMock(LinkRepository::class);
        $repository->expects(self::once())->method('add');

        $shortener = new LinkShortener($repository, $this->generatorReturning('aaaaaaaa'), new NullLogger());

        self::assertSame('aaaaaaaa', $shortener->shorten('https://example.com'));
    }

    #[Test]
    public function retriesWithANewCodeAfterCollision(): void
    {
        $calls = 0;
        // A stub, not a mock: the call count is asserted below by hand.
        $repository = $this->createStub(LinkRepository::class);
        $repository->method('add')->willReturnCallback(function () use (&$calls): void {
            if (++$calls === 1) {
                throw new CodeAlreadyTakenException('aaaaaaaa');
            }
        });

        $shortener = new LinkShortener(
            $repository, $this->generatorReturning('aaaaaaaa', 'bbbbbbbb'), new NullLogger(),
        );

        self::assertSame('bbbbbbbb', $shortener->shorten('https://example.com'));
        self::assertSame(2, $calls);
    }

    #[Test]
    public function doesNotRetryWhenStorageIsBroken(): void
    {
        $repository = $this->createMock(LinkRepository::class);
        // The point: one attempt, not three — retrying a dead database is pointless.
        $repository
            ->expects(self::once())->method('add')
            ->willThrowException(new \RuntimeException('server has gone away'));

        $shortener = new LinkShortener($repository, $this->generatorReturning('aaaaaaaa'), new NullLogger());

        $this->expectException(LinkShortenerException::class);
        $shortener->shorten('https://example.com');
    }
}

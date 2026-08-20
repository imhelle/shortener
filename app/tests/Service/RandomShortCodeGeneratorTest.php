<?php

namespace App\Tests\Service;

use App\Service\RandomShortCodeGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

#[CoversClass(RandomShortCodeGenerator::class)]
final class RandomShortCodeGeneratorTest extends TestCase
{
    private RandomShortCodeGenerator $generator;

    // Rebuilt before every test, so no state leaks between them.
    protected function setUp(): void
    {
        $this->generator = new RandomShortCodeGenerator();
    }

    /**
     * @throws RandomException
     */
    #[Test]
    public function generatesCodeOfExpectedLength(): void
    {
        self::assertSame(8, strlen($this->generator->generate()));
    }

    /**
     * @throws RandomException
     */
    #[Test]
    public function generatesCodeFromAllowedAlphabetOnly(): void
    {
        // Spelled out here on purpose: deriving it from the generator would
        // make the test agree with the code instead of checking it.
        self::assertMatchesRegularExpression('/^[0-9a-zA-Z]{8}$/', $this->generator->generate());
    }

    /**
     * @throws RandomException
     */
    #[Test]
    public function generatesDifferentCodes(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = $this->generator->generate();
        }

        // A trap for a stuck generator, not a proof of randomness.
        self::assertCount(100, array_unique($codes));
    }

    /**
     * @throws RandomException
     */
    #[Test]
    #[DataProvider('lengthCases')]
    public function respectsConfiguredLength(int $length): void
    {
        self::assertSame($length, strlen((new RandomShortCodeGenerator($length))->generate()));
    }

    public static function lengthCases(): iterable
    {
        yield 'минимальная' => [1];
        yield 'обычная' => [8];
        yield 'длинная' => [32];
    }
}

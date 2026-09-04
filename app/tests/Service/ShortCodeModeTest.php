<?php

namespace App\Tests\Service;

use App\Service\ShortCodeMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShortCodeMode::class)]
final class ShortCodeModeTest extends TestCase
{

    #[Test]
    public function strictKeepsCaseAndTheWholeBase62Space(): void
    {
        self::assertSame('aB3xY9Qw', ShortCodeMode::Strict->normalize('aB3xY9Qw'));
        self::assertSame(62, strlen(ShortCodeMode::Strict->alphabet()));
    }

    #[Test]
    public function looseFoldsCaseAndDropsToBase36(): void
    {
        self::assertSame('ab3xy9qw', ShortCodeMode::Loose->normalize('aB3xY9Qw'));
        self::assertSame(36, strlen(ShortCodeMode::Loose->alphabet()));
    }

    /**
     * The invariant that ties the two halves together: if the alphabet held
     * characters that normalization changes, two different generated codes would
     * collapse into one on the way into the table, and collisions would arrive far
     * sooner than the arithmetic promises.
     */
    #[Test]
    public function everyAlphabetSurvivesItsOwnNormalization(): void
    {
        foreach (ShortCodeMode::cases() as $mode) {
            self::assertSame($mode->alphabet(), $mode->normalize($mode->alphabet()), $mode->value);
        }
    }

    /**
     * A repeated character would quietly skew the distribution: it would come up
     * twice as often as the rest, and the real entropy of a code would be lower
     * than its length suggests.
     */
    #[Test]
    public function noAlphabetRepeatsACharacter(): void
    {
        foreach (ShortCodeMode::cases() as $mode) {
            $characters = str_split($mode->alphabet());
            self::assertSame(array_values(array_unique($characters)), $characters, $mode->value);
        }
    }

}

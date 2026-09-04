<?php

namespace App\Service;

/**
 * Which characters a short code is made of, and whether case matters when it
 * comes back in a URL. These are one decision, not two: generating from an
 * alphabet with capitals while comparing case-insensitively would let two
 * different codes collapse into one, so the alphabet and the comparison live
 * here together and cannot be configured apart.
 *
 * The choice is made once, before the first link is handed out. Loose to strict
 * is safe afterwards — codes already stored are lowercase and keep resolving —
 * but strict to loose breaks every code that has a capital in it, and no
 * migration can repair that: "aB" and "Ab" would have to become one row.
 */
enum ShortCodeMode: string
{

    /**
     * Case matters, the whole base62 space is available. For codes that are
     * copied with a click, which is the only way ours reach anyone.
     */
    case Strict = 'strict';

    /**
     * Case is ignored, so the alphabet drops to base36. For codes people retype
     * from paper or read out loud — at the price of a much smaller space: 2.8
     * trillion against 218 trillion at eight characters, which is why a loose
     * setup wants a longer code.
     */
    case Loose = 'loose';

    public function alphabet(): string
    {
        return match ($this) {
            self::Strict => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::Loose => '0123456789abcdefghijklmnopqrstuvwxyz',
        };
    }

    /**
     * Applied to both sides — to a generated code before it is stored, and to the
     * one from the URL before the lookup.
     *
     * Doing it here rather than with a case-insensitive collation is the point:
     * a ci collation also declares 'ß' equal to 'ss' and 'e' equal to 'é', which
     * nobody asked for. This way the column stays binary, "equal" means "byte for
     * byte equal", and the only thing we chose to ignore is case.
     *
     * strtolower and not mb_strtolower: both alphabets above are ASCII, and since
     * PHP 8.2 this function ignores the locale — before that, a Turkish locale
     * turned "I" into dotless "ı" and broke exactly this kind of code.
     */
    public function normalize(string $code): string
    {
        return match ($this) {
            self::Strict => $code,
            self::Loose => strtolower($code),
        };
    }

}

<?php

namespace App\Service;

use App\Service\Exception\InvalidUrlException;
use App\ValueObject\Url;

/**
 * Turns user input into a canonical absolute URL, or refuses it.
 *
 * The rule throughout is "parse, never guess". Input that already carries a
 * scheme is judged as it stands and never patched into something else: most
 * published bypasses of URL validators come from code trying to repair a
 * hostile string instead of rejecting it.
 */
final class UrlNormalizer
{

    /** Mirrors the width of the link.url column. */
    public const MAX_LENGTH = 2048;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** Assumed when the user typed a bare host, e.g. "google.com". */
    private const DEFAULT_SCHEME = 'https';

    /**
     * A host name, never a bare IP address: labels are alphanumeric with inner
     * dashes, and the last one is alphabetic (or punycode). Demanding a letters
     * only TLD rejects 127.0.0.1 together with its octal (0177.0.0.1) and
     * decimal (2130706433) spellings in a single rule, with no table of private
     * ranges to keep up to date.
     */
    private const HOST_PATTERN = '~^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[a-z]{2,}|xn--[a-z0-9-]+)$~';

    /**
     * @throws InvalidUrlException
     */
    public function normalize(string $input): Url
    {
        $url = trim($input);

        if ('' === $url) {
            throw new InvalidUrlException('The URL is empty');
        }

        // Control characters would travel into a Location header. PHP refuses to
        // emit them anyway, so their presence means a typo or an injection try.
        if (1 === preg_match('~[\x00-\x1F\x7F]~', $url)) {
            throw new InvalidUrlException('The URL contains control characters');
        }

        $url = $this->applyScheme($url);
        $parts = parse_url($url);

        if (false === $parts || !isset($parts['host'])) {
            throw new InvalidUrlException('The URL cannot be parsed');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            // https://google.com@evil.com reads as Google but leads elsewhere.
            throw new InvalidUrlException('Credentials in the URL are not allowed');
        }

        $normalized = $this->build($parts);

        if (strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidUrlException(sprintf('The URL is longer than %d characters', self::MAX_LENGTH));
        }

        return Url::fromNormalized($normalized);
    }

    /**
     * @throws InvalidUrlException
     */
    private function applyScheme(string $url): string
    {
        // Protocol-relative input ("//example.com") carries no scheme, but the
        // slashes are already there and must not be doubled.
        if (str_starts_with($url, '//')) {
            return self::DEFAULT_SCHEME . ':' . $url;
        }

        // RFC 3986 scheme: a letter, then letters, digits, "+", "-" or "."
        if (1 !== preg_match('~^([a-z][a-z0-9+.\-]*):(.*)$~is', $url, $matches)) {
            return self::DEFAULT_SCHEME . '://' . $url;
        }

        // "google.com:8080/path" matches the scheme pattern as well, since dots
        // are legal in a scheme name. Digits right after the colon mean a port.
        if (1 === preg_match('~^\d+(?:[/?#]|$)~', $matches[2])) {
            return self::DEFAULT_SCHEME . '://' . $url;
        }

        $scheme = strtolower($matches[1]);

        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            // Refused, not repaired: "javascript:" must never become a stored link.
            throw new InvalidUrlException(sprintf('Scheme "%s" is not allowed', $scheme));
        }

        return $url;
    }

    /**
     * @param array<string, string|int> $parts
     *
     * @throws InvalidUrlException
     */
    private function build(array $parts): string
    {
        $host = $this->normalizeHost((string) $parts['host']);
        $url = strtolower((string) $parts['scheme']) . '://' . $host;

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        // Everything past the host stays byte for byte as typed: paths are case-sensitive
        // and re-encoding them would change what the server receives.
        $url .= $parts['path'] ?? '';

        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    /**
     * @throws InvalidUrlException
     */
    private function normalizeHost(string $host): string
    {
        // A Location header must be ASCII, so an IDN is stored as punycode.
        if (1 === preg_match('~[^\x20-\x7E]~', $host)) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

            if (false === $ascii) {
                throw new InvalidUrlException(sprintf('Host "%s" cannot be converted to punycode', $host));
            }

            $host = $ascii;
        }

        $host = strtolower($host);

        if (1 !== preg_match(self::HOST_PATTERN, $host)) {
            throw new InvalidUrlException(sprintf('"%s" is not a valid host name', $host));
        }

        return $host;
    }

}

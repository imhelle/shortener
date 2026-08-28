<?php

namespace App\Tests\Service;

use App\Service\Exception\InvalidUrlException;
use App\Service\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{

    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UrlNormalizer();
    }

    #[DataProvider('acceptedUrls')]
    public function testNormalizesAcceptableUrl(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($input)->toString());
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectsUnacceptableUrl(string $input): void
    {
        $this->expectException(InvalidUrlException::class);

        $this->normalizer->normalize($input);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedUrls(): iterable
    {
        yield 'bare host gets https' => ['google.com', 'https://google.com'];
        yield 'surrounding spaces' => ['  google.com  ', 'https://google.com'];
        yield 'protocol relative' => ['//google.com/a', 'https://google.com/a'];
        yield 'plain http is kept' => ['http://example.com/', 'http://example.com/'];
        yield 'scheme case folded' => ['HTTPS://Example.COM/Path', 'https://example.com/Path'];
        yield 'host with port' => ['example.com:8080/a', 'https://example.com:8080/a'];
        yield 'query and fragment' => ['example.com/a?b=1&c=2#top', 'https://example.com/a?b=1&c=2#top'];
        yield 'idn becomes punycode' => ['пример.рф', 'https://xn--e1afmkfd.xn--p1ai'];
        yield 'punycode stays' => ['xn--e1afmkfd.xn--p1ai', 'https://xn--e1afmkfd.xn--p1ai'];
        yield 'dashes in labels' => ['my-site.co.uk', 'https://my-site.co.uk'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'javascript with authority' => ['javascript://evil.com/%0aalert(1)'];
        yield 'data scheme' => ['data:text/html,<script>alert(1)</script>'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'credentials' => ['https://google.com@evil.com'];
        yield 'no tld' => ['http://foo'];
        yield 'localhost' => ['http://localhost:8080/'];
        yield 'docker service name' => ['http://db:3306'];
        yield 'loopback ip' => ['http://127.0.0.1/'];
        yield 'loopback ip in octal' => ['http://0177.0.0.1/'];
        yield 'loopback ip in decimal' => ['http://2130706433/'];
        yield 'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'ipv6 loopback' => ['http://[::1]/'];
        yield 'header injection' => ["https://example.com/\r\nX-Injected: 1"];
        yield 'too long' => ['https://example.com/' . str_repeat('a', UrlNormalizer::MAX_LENGTH)];
    }

}

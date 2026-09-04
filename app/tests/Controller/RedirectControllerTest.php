<?php

namespace App\Tests\Controller;

use App\Controller\RedirectController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(RedirectController::class)]
final class RedirectControllerTest extends WebTestCase
{

    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('TRUNCATE TABLE link');
    }

    #[Test]
    public function sendsAKnownCodeToItsUrl(): void
    {
        $this->store('aB3xY9Qw', 'https://example.com/deep/path?utm=1');

        $this->client->request('GET', '/aB3xY9Qw');

        // 302, not 301: a permanent redirect would be cached by the browser for
        // good, and the target could never be changed or taken down.
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        self::assertResponseRedirects('https://example.com/deep/path?utm=1');
    }

    #[Test]
    public function treatsCodesAsCaseSensitive(): void
    {
        $this->store('aB3xY9Qw', 'https://example.com/');

        // The column is ascii_bin, so this is a different code, not the same one
        // in other case. Under the default collation it would have matched and
        // sent the visitor to somebody else's link.
        $this->client->request('GET', '/AB3XY9QW');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function answers404ForAnUnknownCode(): void
    {
        $this->client->request('GET', '/zzzzzzzz');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function doesNotEvenLookUpANonAsciiPath(): void
    {
        // Eight characters, so only the character class in the route keeps this
        // away from the query. Without it MySQL answers error 1267 comparing
        // utf8mb4 against an ascii_bin column, and a 404 turns into a 500.
        $this->client->request('GET', '/приветик');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function doesNotMatchAPathOfTheWrongLength(): void
    {
        $this->store('aB3xY9Qw', 'https://example.com/');

        $this->client->request('GET', '/aB3xY9Q');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function store(string $code, string $url): void
    {
        $this->connection->insert('link', [
            'code' => $code,
            'url' => $url,
            'created_at' => '2026-09-04 12:00:00',
        ]);
    }

}

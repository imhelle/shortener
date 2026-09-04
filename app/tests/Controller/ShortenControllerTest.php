<?php

namespace App\Tests\Controller;

use App\Controller\ShortenController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * These run against a real database, unlike the domain tests. The border with
 * storage is exactly where the interesting failures live — a unique index, an
 * ascii_bin column, a host that has to reach the table already in punycode —
 * and a fake repository would have nothing to say about any of them.
 */
#[CoversClass(ShortenController::class)]
final class ShortenControllerTest extends WebTestCase
{

    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);
        // Every test starts from an empty table, which is what makes the row
        // counts below assertions rather than guesses.
        $this->connection->executeStatement('TRUNCATE TABLE link');
    }

    #[Test]
    public function servesTheFormWithACsrfToken(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="url"]');
        // Without the hidden field every submission below would answer 403, and
        // the rest of this suite would pass for the wrong reason.
        self::assertSelectorExists('form input[name="_csrf_token"]');
    }

    #[Test]
    public function shortensAndRedirectsInsteadOfAnsweringThePost(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Shorten', ['url' => 'example.com/path']);

        // 303, not 302: the browser must fetch the result with a separate GET.
        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertResponseRedirects('/');

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Done');
        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function reloadingTheResultCreatesNothing(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Shorten', ['url' => 'example.com/path']);
        $this->client->followRedirect();

        // What F5 does after the redirect: the same GET again.
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        // The flash was read once and cleared with it, so the result is gone.
        self::assertSelectorTextNotContains('body', 'Done');
        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function storesTheNormalizedUrl(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Shorten', ['url' => 'ПРЕЗИДЕНТ.РФ/путь']);
        $this->client->followRedirect();

        // The row holds what will go into Location, not what was typed.
        self::assertSame(
            'https://xn--d1abbgf6aiiy.xn--p1ai/путь',
            $this->connection->fetchOne('SELECT url FROM link'),
        );
    }

    #[Test]
    public function refusesEmptyInput(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Shorten', ['url' => '']);

        // 422, not 400: the request is well formed, its content is not.
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('[role="alert"]', 'Please enter a link.');
        self::assertSame(0, $this->rowCount());
    }

    #[Test]
    public function refusesASchemeThatIsNotHttp(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Shorten', ['url' => 'javascript:alert(1)']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->rowCount());
    }

    #[Test]
    public function refusesASubmissionWithoutACsrfToken(): void
    {
        // Straight to the endpoint, the way a third-party page would post: the
        // browser would attach the cookies, but it cannot read our token.
        $this->client->request('POST', '/', ['url' => 'example.com']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, $this->rowCount());
    }

    private function rowCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM link');
    }

}

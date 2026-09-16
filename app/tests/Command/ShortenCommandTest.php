<?php

namespace App\Tests\Command;

use App\Command\ShortenCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Driven through the real kernel rather than with a stubbed shortener: half of
 * what can go wrong here is not in the class at all — the command has to be
 * registered under its name, built out of the container, and able to run where
 * there is no request at all.
 */
#[CoversClass(ShortenCommand::class)]
final class ShortenCommandTest extends KernelTestCase
{

    private CommandTester $command;
    private Connection $connection;

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());

        $this->command = new CommandTester($application->find('app:shorten'));
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('TRUNCATE TABLE link');
    }

    #[Test]
    public function shortensAndReportsTheCodeItStored(): void
    {
        $exitCode = $this->command->execute(['url' => 'example.com/path']);

        // 0 written out rather than Command::SUCCESS: what matters is the number
        // the shell sees, and a script chaining this command reads nothing else.
        self::assertSame(0, $exitCode);
        self::assertSame(1, $this->rowCount());

        // The code in the row and the code on screen have to be the same one,
        // and the URL reported is the stored one — "example.com/path" went in.
        $row = $this->connection->fetchAssociative('SELECT code, url FROM link');
        self::assertSame('https://example.com/path', $row['url']);
        self::assertStringContainsString($row['code'], $this->command->getDisplay());
        self::assertStringContainsString('https://example.com/path', $this->command->getDisplay());
    }

    #[Test]
    public function answersAnUnacceptableUrlWithANonZeroExitCode(): void
    {
        $exitCode = $this->command->execute(['url' => 'javascript:alert(1)']);

        // 1, not 0: the failure has to be visible to whatever called this.
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Scheme "javascript" is not allowed', $this->command->getDisplay());
        self::assertSame(0, $this->rowCount());
    }

    #[Test]
    public function issuesAFreshCodeEveryRun(): void
    {
        // The memory of already shortened links lives in the session, and on a
        // command line there is none. Two runs of the same address are two
        // strangers — which is the documented behaviour, not an oversight: the
        // feature exists to spare one visitor a duplicate, and two invocations
        // of a CLI command share no visitor.
        $this->command->execute(['url' => 'example.com/path']);
        $this->command->execute(['url' => 'example.com/path']);

        self::assertSame(2, $this->rowCount());
        self::assertCount(2, $this->connection->fetchFirstColumn('SELECT DISTINCT code FROM link'));
    }

    private function rowCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM link');
    }

}

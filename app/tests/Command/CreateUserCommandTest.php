<?php

namespace App\Tests\Command;

use App\Command\CreateUserCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Through the real kernel and the real database, like app:shorten: the two
 * things most worth checking here are not in the class — which hasher the
 * container hands over, and what the unique index on email considers equal.
 */
#[CoversClass(CreateUserCommand::class)]
final class CreateUserCommandTest extends KernelTestCase
{

    private CommandTester $command;
    private Connection $connection;

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());

        $this->command = new CommandTester($application->find('app:user:create'));
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('TRUNCATE TABLE app_user');
    }

    #[Test]
    public function storesAHashThatTheGivenPasswordMatches(): void
    {
        $this->command->setInputs(['correct horse']);
        $exitCode = $this->command->execute(['email' => 'olga@example.com']);

        self::assertSame(0, $exitCode);

        $row = $this->connection->fetchAssociative('SELECT email, password FROM app_user');
        self::assertSame('olga@example.com', $row['email']);
        // Not compared with a hash computed here: every hash carries its own
        // salt, so two of the same password never match as strings. What has
        // to hold is that the stored value is not the password, and that the
        // password is accepted by it.
        self::assertNotSame('correct horse', $row['password']);
        self::assertTrue(password_verify('correct horse', $row['password']));
    }

    #[Test]
    public function refusesAnAddressThatAlreadyExists(): void
    {
        $this->createUser('olga@example.com');

        $this->command->setInputs(['another one']);
        $exitCode = $this->command->execute(['email' => 'olga@example.com']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('already exists', $this->command->getDisplay());
        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function refusesTheSameAddressInAnotherCase(): void
    {
        // A test of the column, not of the command: nothing in the code
        // lowercases the address, the _ci collation is what makes these two
        // one row. Switch the column to a binary collation and this is the
        // test that fails — which is exactly the decision it is here to pin.
        $this->createUser('olga@example.com');

        $this->command->setInputs(['another one']);
        $exitCode = $this->command->execute(['email' => 'Olga@Example.COM']);

        self::assertSame(1, $exitCode);
        self::assertSame(1, $this->rowCount());
    }

    #[Test]
    public function refusesAnEmptyPassword(): void
    {
        // A bare Enter at the prompt.
        $this->command->setInputs(['']);
        $exitCode = $this->command->execute(['email' => 'olga@example.com']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('cannot be empty', $this->command->getDisplay());
        self::assertSame(0, $this->rowCount());
    }

    private function createUser(string $email): void
    {
        $this->command->setInputs(['a password']);
        self::assertSame(0, $this->command->execute(['email' => $email]));
    }

    private function rowCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_user');
    }

}

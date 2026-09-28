<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The only way to put a row in app_user, and for now the only reason the table
 * is not empty: nothing else creates users, because there is no registration.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user with an email and a password',
)]
class CreateUserCommand extends Command
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The address the user will sign in with');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        // Asked for, never taken as an argument: an argument is kept in the
        // shell history and is readable in `ps` by every account on the machine
        // for as long as the command runs. The prompt costs one line and the
        // secret never becomes a string anyone else can go back and find.
        $password = $io->askHidden('Password');

        // An empty answer is what a bare Enter produces, and hidden input gives
        // no way to notice it was one. Hashing it would succeed and store a
        // perfectly usable hash of nothing at all.
        if (null === $password || '' === $password) {
            $io->error('The password cannot be empty');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        // hashPassword takes the user, not just the string: which algorithm
        // applies is configured per class in security.yaml, so the object has
        // to be there for the hasher to know what it is dealing with.
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // The index does the checking, not a SELECT before the INSERT: two
            // runs at once would both pass such a check and one would still
            // fail here. Nothing is retried, so the closed EntityManager that a
            // failed flush leaves behind costs us nothing -- the command ends.
            $io->error("{$email} already exists");

            return Command::FAILURE;
        }

        $io->success("{$email} has been created");

        return Command::SUCCESS;
    }
}

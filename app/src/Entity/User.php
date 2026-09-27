<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
// Not `user`: that is a reserved word in standard SQL and would need quoting
// on any platform stricter than ours. The prefix costs nothing and removes
// a whole class of surprises we would only meet on the day we move.
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The collation is left to the server (here utf8mb4_0900_ai_ci), which is
     * what make:user writes and what most Symfony applications run with. It buys
     * the behaviour a user expects — Olga@x.com signs in as olga@x.com — without
     * a line of code: the entity provider hands the identifier straight to
     * findOneBy(), so the comparison is the column's, not ours.
     *
     * Two prices, both accepted knowingly. Accents and ligatures arrive in the
     * same package, so e@x.com and é@x.com cannot both be registered. And the
     * rule lives in the schema rather than in code, so a platform where "=" is
     * exact would drop case-insensitive sign-in without a word.
     */
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * Every token this user was issued before this moment is refused.
     *
     * A JWT carries its own answer to "may this request proceed" and asks no
     * one, which is why it cannot be taken back. This column buys the ability
     * back for the price of one comparison against the token's iat, on a row
     * that authentication reads anyway. Null means nothing was ever revoked.
     *
     * The granularity is deliberately coarse: it revokes every token this
     * user holds, not a chosen one. Singling out a token needs a list of
     * identifiers, and that is a different feature with a different cost.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $tokensValidAfter = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getTokensValidAfter(): ?\DateTimeImmutable
    {
        return $this->tokensValidAfter;
    }

    public function setTokensValidAfter(?\DateTimeImmutable $tokensValidAfter): static
    {
        $this->tokensValidAfter = $tokensValidAfter;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}

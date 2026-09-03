<?php

namespace App\Entity;

use App\Repository\LinkRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The database schema, written in PHP: Doctrine reads these attributes to
 * generate migrations and to check the mapping against the real table.
 *
 * Nothing constructs this class and nothing reads it. Writes go through
 * NewLink and raw SQL, because a failed flush() closes the EntityManager,
 * which would make a retry after a code collision impossible; the redirect
 * reads its one column with raw SQL for the same kind of reason. A constructor
 * and getters would therefore be dead code, and Doctrine needs neither: the
 * mapping reads the properties directly, and hydration bypasses constructors.
 * They come back the day reading goes through the ORM.
 */
#[ORM\Entity(repositoryClass: LinkRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_link_code', columns: ['code'])]
class Link
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8, options: ['fixed' => true, 'charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $code;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

}

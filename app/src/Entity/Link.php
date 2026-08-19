<?php

namespace App\Entity;

use App\Repository\LinkRepository;
use Doctrine\ORM\Mapping as ORM;

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

    public function __construct( string $url, string $code, ?\DateTimeImmutable $createdAt = null)
    {
        $this->url = $url;
        $this->code = $code;
        $this->createdAt =  $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

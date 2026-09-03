<?php

namespace App\Repository;

use App\Entity\Link;
use App\Service\Exception\CodeAlreadyTakenException;
use App\Service\LinkStorageInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Link>
 */
class LinkRepository extends ServiceEntityRepository implements LinkStorageInterface
{
    public function __construct(ManagerRegistry $registry, private readonly Connection $connection)
    {
        parent::__construct($registry, Link::class);
    }

    /**
     * @throws Exception
     */
    public function add(Link $link): void
    {
        try {
            $this->connection->insert('link', [
                'code'       => $link->getCode(),
                'url'        => $link->getUrl(),
                'created_at' => $link->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new CodeAlreadyTakenException($link->getCode(), previous: $e);
        }
    }

    /**
     * Raw SQL on purpose: the redirect needs one column, not a hydrated entity.
     *
     * @throws Exception
     */
    public function findUrlByCode(string $code): ?string
    {
        // fetchOne() returns false when no row matched, unlike a null column value.
        $url = $this->connection->fetchOne(
            'SELECT url FROM link WHERE code = ?',
            [$code],
        );

        return false === $url ? null : $url;
    }

    //    /**
    //     * @return Link[] Returns an array of Link objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Link
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

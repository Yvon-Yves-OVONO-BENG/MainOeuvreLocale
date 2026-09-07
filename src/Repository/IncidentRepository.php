<?php

namespace App\Repository;

use App\Entity\Incident;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    public function findRecentOpenByFingerprint(string $fingerprint, \DateTimeImmutable $since): ?Incident
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.fingerprint = :fingerprint')
            ->andWhere('i.status = :status')
            ->andWhere('i.lastOccurredAt >= :since')
            ->setParameter('fingerprint', $fingerprint)
            ->setParameter('status', Incident::STATUS_OPEN)
            ->setParameter('since', $since)
            ->orderBy('i.lastOccurredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Incident[] */
    public function findLatest(int $limit = 250): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')->addSelect('u')
            ->orderBy('i.lastOccurredAt', 'DESC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->setParameter('status', Incident::STATUS_OPEN)
            ->getQuery()->getSingleScalarResult();
    }
}

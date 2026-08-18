<?php

namespace App\Repository;

use App\Entity\SecurityIncident;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SecurityIncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityIncident::class);
    }

    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')->addSelect('u')
            ->leftJoin('i.assignedTo', 'a')->addSelect('a')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBySeverity(string $severity): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.severity = :severity')
            ->setParameter('severity', $severity)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countMitigatedSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->andWhere('i.updatedAt >= :since')
            ->setParameter('status', SecurityIncident::STATUS_MITIGATED)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
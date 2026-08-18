<?php

namespace App\Repository;

use App\Entity\KycRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class KycRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KycRule::class);
    }


    public function countEnabledRules(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.enabled = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestChangedAt(): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('k')
            ->select('k.updatedAt, k.createdAt')
            ->orderBy('k.updatedAt', 'DESC')
            ->addOrderBy('k.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || !\is_array($result)) {
            return null;
        }

        return $result['updatedAt'] ?? $result['createdAt'] ?? null;
    }

    
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.groupName', 'ASC')
            ->addOrderBy('k.level', 'DESC')
            ->addOrderBy('k.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countEnabled(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countHigh(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.level = :level')
            ->setParameter('level', KycRule::LEVEL_HIGH)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countGroups(): int
    {
        $rows = $this->createQueryBuilder('k')
            ->select('DISTINCT k.groupName')
            ->getQuery()
            ->getArrayResult();

        return count($rows);
    }

    public function findDistinctGroups(): array
    {
        $rows = $this->createQueryBuilder('k')
            ->select('DISTINCT k.groupName AS groupName')
            ->orderBy('k.groupName', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn(array $row) => $row['groupName'] ?? null,
            $rows
        )));
    }


    public function countDisabled(): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.enabled = :enabled')
            ->setParameter('enabled', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
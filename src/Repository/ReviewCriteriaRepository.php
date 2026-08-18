<?php

namespace App\Repository;

use App\Entity\ReviewCriteria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReviewCriteriaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewCriteria::class);
    }

    /**
     * Critères actifs par type
     */
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.targetType = :type')
            ->andWhere('c.active = true')
            ->setParameter('type', $type)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
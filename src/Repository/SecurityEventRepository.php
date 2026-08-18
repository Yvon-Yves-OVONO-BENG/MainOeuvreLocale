<?php

namespace App\Repository;

use App\Entity\SecurityEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SecurityEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityEvent::class);
    }

    public function findLatestOpenOrReview(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')->addSelect('u')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('statuses', [
                SecurityEvent::STATUS_OPEN,
                SecurityEvent::STATUS_REVIEW,
            ])
            ->orderBy('e.detectedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
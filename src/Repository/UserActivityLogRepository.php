<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserActivityLog::class);
    }

    public function findRecentByUser(User $user, int $limit = 25): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :u')->setParameter('u', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
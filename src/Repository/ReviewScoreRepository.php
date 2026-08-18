<?php

namespace App\Repository;

use App\Entity\ReviewCriteria;
use App\Entity\ReviewScore;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReviewScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewScore::class);
    }

    /**
     * Moyenne d'un critère
     */
    public function getAverageByCriteria(
        User $user,
        ReviewCriteria $criteria
    ): float {

        $result = $this->createQueryBuilder('s')
            ->select('AVG(s.score) avg')
            ->join('s.review', 'r')
            ->where('r.target = :user')
            ->andWhere('s.criteria = :criteria')
            ->setParameter('user', $user)
            ->setParameter('criteria', $criteria)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)$result, 1);
    }

    /**
     * Toutes les statistiques d'un utilisateur
     */
    public function getStatistics(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->select(
                'c.id,
                 c.name,
                 ROUND(AVG(s.score),1) average,
                 COUNT(s.id) voters'
            )
            ->join('s.criteria', 'c')
            ->join('s.review', 'r')
            ->where('r.target = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
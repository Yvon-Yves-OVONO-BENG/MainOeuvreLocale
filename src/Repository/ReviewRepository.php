<?php

namespace App\Repository;

use App\Entity\ProfessionalProfile;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }


    /**
     * Stats pour un utilisateur (moyenne + nombre de votants)
     *
     * @param int $ratedUserId
     * @return array ['avg' => float, 'voters' => int]
     */
    public function getStatsForUser(int $ratedUserId): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COALESCE(AVG(r.globalScore), 0) AS avg', 'COUNT(r.id) AS voters')
            ->andWhere('r.target = :u')
            ->setParameter('u', $ratedUserId)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'avgRating' => (float) ($row['avg']/2 ?? 0),
            'voters' => (int) ($row['voters'] ?? 0),
        ];
    }

    /**
     * ✅ 2 derniers avis reçus (target = moi)
     */
    public function findLatestReceived(User $me, int $limit = 2): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.target = :me')
            ->setParameter('me', $me)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    /** @return Review[] */
   public function findLatestForProfile(User $targetUser, int $limit = 3): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.author', 'author')
            ->addSelect('author')
            ->andWhere('r.target = :u')
            ->andWhere('r.published = true')
            ->setParameter('u', $targetUser)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Review[] */
    public function findAllForProfile(User $targetUser): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.author', 'author')
            ->addSelect('author')
            ->andWhere('r.target = :u')
            ->andWhere('r.published = true')
            ->setParameter('u', $targetUser)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Avis reçus (les gens qui ont commenté l'utilisateur)
     */
    public function findReceivedByUser(User $me, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.author', 'author')
            ->addSelect('author')
            ->andWhere('r.target = :me')
            ->setParameter('me', $me)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Avis donnés (les gens que l'utilisateur a commenté)
     *
     * @param User $me
     * @param int $limit
     * @return array
     */
    public function findGivenByUser(User $me, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.target', 'target')
            ->addSelect('target')
            ->andWhere('r.author = :me')
            ->setParameter('me', $me)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre d'avis reçus par un utilisateur
     * @param User $me
     * @return int
     */
    public function countReceived(User $me): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.target = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre d'avis donnés par un utilisateur
     * @param User $me
     * @return int
     */
    public function countGiven(User $me): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.author = :me')
            ->setParameter('me', $me)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function findMine(User $target, User $me): ?Review
    {
        return $this->findOneBy(['author' => $me, 'target' => $target]);
    }

    public function findForTarget(User $target, int $limit = 50): array
    {
        return $this->findBy(
            ['target' => $target, 'published' => true],
            ['createdAt' => 'DESC'],
            $limit
        );
    }
    
    public function findLastReviews(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.author', 'author')
            ->addSelect('author')
            ->where('r.target = :user')
            ->andWhere('r.published = true')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    
    public function getStatsForUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
    
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.target) as userId')
            ->addSelect('COUNT(r.id) as reviews')
            ->addSelect('AVG(r.globalScore) as avgScore')
            ->andWhere('r.target IN (:users)')
            ->andWhere('r.published = true')
            ->setParameter('users', $userIds)
            ->groupBy('r.target')
            ->getQuery()
            ->getArrayResult();
    
        $map = [];
    
        foreach ($rows as $row) {
    
            $map[(int)$row['userId']] = [
                'avg' => round((float)$row['avgScore']/ 2, 1),
                'voters' => (int)$row['reviews']
            ];
        }
    
        return $map;
    }
    
    
    public function getStatsForOneUser(User $user): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) as reviews')
            ->addSelect('AVG(r.globalScore) as avgScore')
            ->andWhere('r.target = :user')
            ->andWhere('r.published = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    
        return [
            'avg' => round((float)($row['avgScore'] ?? 0), 1),
            'voters'   => (int)($row['reviews'] ?? 0),
        ];
    }
    
    

    //    /**
    //     * @return Review[] Returns an array of Review objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Review
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

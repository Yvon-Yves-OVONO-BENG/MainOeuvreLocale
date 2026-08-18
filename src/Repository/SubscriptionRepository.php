<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findActiveSubscriptionByUser(int $userId): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :userId')
            ->andWhere('s.isActive = true')
            ->andWhere('s.endAt IS NULL OR s.endAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findExpiredSubscriptions(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->andWhere('s.endAt IS NOT NULL')
            ->andWhere('s.endAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function getUsersWithPlan(string $planSlug): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.plan', 'p')
            ->where('p.slug = :planSlug')
            ->andWhere('s.isActive = true')
            ->andWhere('s.endAt IS NULL OR s.endAt > :now')
            ->setParameter('planSlug', $planSlug)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function countAllSubscriptions(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveSubscriptions(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.endAt > :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countExpiredSubscriptions(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.endAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNewSubscriptionsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.startAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getSubscriptionsChartSince(\DateTimeInterface $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DATE(start_at) AS d, COUNT(id) AS c
            FROM subscription
            WHERE start_at >= :since
            GROUP BY DATE(start_at)
            ORDER BY d ASC
        ';

        return $conn->executeQuery(
            $sql,
            ['since' => $since->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();
    }

    public function findRecentSubscriptions(int $limit = 8): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->leftJoin('s.plan', 'p')->addSelect('p')
            ->leftJoin('s.status', 'st')->addSelect('st')
            ->orderBy('s.startAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTopPlansSince(\DateTimeInterface $since, int $limit = 8): array
    {
        return $this->createQueryBuilder('s')
            ->select('p.plan AS planName, COUNT(s.id) AS total')
            ->leftJoin('s.plan', 'p')
            ->andWhere('s.startAt >= :since')
            ->setParameter('since', $since)
            ->andWhere('p.id IS NOT NULL')
            ->groupBy('p.id, p.plan')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Subscription[] Returns an array of Subscription objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Subscription
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

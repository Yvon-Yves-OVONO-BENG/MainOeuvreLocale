<?php

namespace App\Repository;

use App\Entity\FavoriJob;
use App\Entity\Job;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriJob>
 *
 * @method FavoriJob|null find($id, $lockMode = null, $lockVersion = null)
 * @method FavoriJob|null findOneBy(array $criteria, array $orderBy = null)
 * @method FavoriJob[]    findAll()
 * @method FavoriJob[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FavoriJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriJob::class);
    }

    // src/Repository/FavoriJobRepository.php
    public function isFavorited(\App\Entity\User $user, \App\Entity\Job $job): bool
    {
        return (bool) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.user = :u')->setParameter('u', $user)
            ->andWhere('f.job = :j')->setParameter('j', $job)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countFavorites(\App\Entity\Job $job): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.job = :j')->setParameter('j', $job)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function findJobFavoritesByUser(User $user): array
    {
        return $this->createQueryBuilder('fj')
            ->leftJoin('fj.job', 'j')->addSelect('j')
            ->andWhere('fj.user = :u')
            ->setParameter('u', $user)
            ->orderBy('fj.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function countFavoritesByCompanyPeriod(
        User $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Job $job = null
    ): int {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->innerJoin('f.job', 'j')
            ->andWhere('j.createdBy = :u')
            ->andWhere('f.createdAt BETWEEN :from AND :to')
            ->setParameter('u', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($job) {
            $qb->andWhere('f.job = :job')->setParameter('job', $job);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    //    /**
    //     * @return FavoriJob[] Returns an array of FavoriJob objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?FavoriJob
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

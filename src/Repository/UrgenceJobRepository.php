<?php

namespace App\Repository;

use App\Entity\UrgenceJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UrgenceJob>
 *
 * @method UrgenceJob|null find($id, $lockMode = null, $lockVersion = null)
 * @method UrgenceJob|null findOneBy(array $criteria, array $orderBy = null)
 * @method UrgenceJob[]    findAll()
 * @method UrgenceJob[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UrgenceJob::class);
    }

    //    /**
    //     * @return UrgenceJob[] Returns an array of UrgenceJob objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UrgenceJob
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

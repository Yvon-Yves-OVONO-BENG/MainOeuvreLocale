<?php

namespace App\Repository;

use App\Entity\TypeJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeJob>
 *
 * @method TypeJob|null find($id, $lockMode = null, $lockVersion = null)
 * @method TypeJob|null findOneBy(array $criteria, array $orderBy = null)
 * @method TypeJob[]    findAll()
 * @method TypeJob[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TypeJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeJob::class);
    }

    //    /**
    //     * @return TypeJob[] Returns an array of TypeJob objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TypeJob
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

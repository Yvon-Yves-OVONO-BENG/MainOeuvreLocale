<?php

namespace App\Repository;

use App\Entity\TypeCompte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeCompte>
 *
 * @method TypeCompte|null find($id, $lockMode = null, $lockVersion = null)
 * @method TypeCompte|null findOneBy(array $criteria, array $orderBy = null)
 * @method TypeCompte[]    findAll()
 * @method TypeCompte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TypeCompteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeCompte::class);
    }

    //    /**
    //     * @return TypeCompte[] Returns an array of TypeCompte objects
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

    //    public function findOneBySomeField($value): ?TypeCompte
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

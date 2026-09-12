<?php

namespace App\Repository;

use App\Entity\StatusJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StatusJob>
 */
class StatusJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatusJob::class);
    }

    /** Résout le statut de publication par son libellé, sans dépendre d'un identifiant fixe. */
    public function findPublished(): ?StatusJob
    {
        foreach ($this->findBy([], ['id' => 'ASC']) as $status) {
            $label = strtr(mb_strtoupper(trim((string) $status->getStatusJob()), 'UTF-8'), ['É' => 'E', 'È' => 'E', 'Ê' => 'E']);
            if (in_array($label, ['PUBLIE', 'PUBLIEE', 'PUBLISHED'], true)) {
                return $status;
            }
        }
        return null;
    }

    //    /**
    //     * @return StatusJob[] Returns an array of StatusJob objects
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

    //    public function findOneBySomeField($value): ?StatusJob
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

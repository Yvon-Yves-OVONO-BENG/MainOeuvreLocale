<?php

namespace App\Repository;

use App\Entity\Annonce;
use App\Entity\BoosteAnnonce;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoosteAnnonce>
 */
final class BoosteAnnonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoosteAnnonce::class);
    }

    public function findPendingForAnnonce(Annonce $annonce, User $user): ?BoosteAnnonce
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.annonce = :annonce')
            ->andWhere('b.user = :user')
            ->andWhere('b.statut = :statut')
            ->setParameter('annonce', $annonce)
            ->setParameter('user', $user)
            ->setParameter('statut', BoosteAnnonce::STATUS_PENDING_PAYMENT)
            ->orderBy('b.dateDemande', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return BoosteAnnonce[] */
    public function findRecentForAnnonce(Annonce $annonce, User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.annonce = :annonce')
            ->andWhere('b.user = :user')
            ->setParameter('annonce', $annonce)
            ->setParameter('user', $user)
            ->orderBy('b.dateDemande', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }
}

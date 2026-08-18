<?php

namespace App\Repository;

use App\Entity\ProfessionalProfile;
use App\Entity\User;
use App\Entity\View;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, View::class);
    }

    public function countTotalViewsForProfile(int $profileId): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.professionalProfile = :p')
            ->setParameter('p', $profileId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueViewersForProfile(int $profileId): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.viewer)')
            ->andWhere('v.professionalProfile = :p')
            ->setParameter('p', $profileId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestViewersForProfile(int $profileId, int $limit = 15): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.viewer', 'u')->addSelect('u')
            ->andWhere('v.professionalProfile = :p')
            ->setParameter('p', $profileId)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Derniers visiteurs d’un profil pro */
    public function findLatestVisitors(ProfessionalProfile $pp, int $limit = 40): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.viewer', 'u')->addSelect('u')
            ->andWhere('v.professionalProfile = :pp')
            ->setParameter('pp', $pp)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Total des vues d’un profil pro */
    public function countViews(ProfessionalProfile $pp): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.professionalProfile = :pp')
            ->setParameter('pp', $pp)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

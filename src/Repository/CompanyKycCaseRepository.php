<?php

namespace App\Repository;

use App\Entity\CompanyKycCase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompanyKycCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyKycCase::class);
    }

    public function countApprovedCompanies(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->setParameter('status', CompanyKycCase::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingCompanies(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', [
                CompanyKycCase::STATUS_PENDING,
                CompanyKycCase::STATUS_IN_REVIEW,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestPending(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.company', 'u')->addSelect('u')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', [
                CompanyKycCase::STATUS_PENDING,
                CompanyKycCase::STATUS_IN_REVIEW,
            ])
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    /**
     * Queue KYC pour le dashboard
     */
    public function findPendingQueue(int $limit = 5): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.company', 'u')
            ->leftJoin('u.personalProfile', 'pp')
            ->addSelect('u', 'pp')
            ->andWhere('k.status IN (:statuses)')
            ->setParameter('statuses', [
                CompanyKycCase::STATUS_PENDING,
                CompanyKycCase::STATUS_IN_REVIEW,
            ])
            ->addSelect("
                CASE
                    WHEN k.riskLevel = 'high' THEN 1
                    WHEN k.riskLevel = 'medium' THEN 2
                    ELSE 3
                END AS HIDDEN riskRank
            ")
            ->orderBy('riskRank', 'ASC')
            ->addOrderBy('k.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * File complète admin
     */
    public function findFullPendingQueue(): array
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.company', 'u')
            ->leftJoin('u.personalProfile', 'pp')
            ->addSelect('u', 'pp')
            ->andWhere('k.status IN (:statuses)')
            ->setParameter('statuses', [
                CompanyKycCase::STATUS_PENDING,
                CompanyKycCase::STATUS_IN_REVIEW,
            ])
            ->addSelect("
                CASE
                    WHEN k.riskLevel = 'high' THEN 1
                    WHEN k.riskLevel = 'medium' THEN 2
                    ELSE 3
                END AS HIDDEN riskRank
            ")
            ->orderBy('riskRank', 'ASC')
            ->addOrderBy('k.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneWithRelations(int $id): ?CompanyKycCase
    {
        return $this->createQueryBuilder('k')
            ->leftJoin('k.company', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('k.documents', 'd')->addSelect('d')
            ->leftJoin('k.reviews', 'r')->addSelect('r')
            ->leftJoin('r.reviewer', 'reviewer')->addSelect('reviewer')
            ->andWhere('k.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
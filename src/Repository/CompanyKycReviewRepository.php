<?php

namespace App\Repository;

use App\Entity\CompanyKycReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompanyKycReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyKycReview::class);
    }

    public function findByCaseOrdered(int $caseId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.adminUser', 'a')->addSelect('a')
            ->andWhere('r.kycCase = :caseId')
            ->setParameter('caseId', $caseId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
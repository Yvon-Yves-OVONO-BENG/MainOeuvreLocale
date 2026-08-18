<?php

namespace App\Repository;

use App\Entity\CompanyKycDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompanyKycDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyKycDocument::class);
    }

    public function findByCaseOrdered(int $caseId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.kycCase = :caseId')
            ->setParameter('caseId', $caseId)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
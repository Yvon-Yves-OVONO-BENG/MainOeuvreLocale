<?php

namespace App\Repository;

use App\Entity\SecurityPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SecurityPermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityPermission::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.roles', 'r')->addSelect('r')
            ->orderBy('p.domain', 'ASC')
            ->addOrderBy('p.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
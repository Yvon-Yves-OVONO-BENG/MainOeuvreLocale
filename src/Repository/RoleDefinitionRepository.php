<?php

namespace App\Repository;

use App\Entity\RoleDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoleDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleDefinition::class);
    }

    public function findIndexedByCode(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')->addSelect('p')
            ->orderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getCode()] = $row;
        }

        return $indexed;
    }
}
<?php

namespace App\Repository;

use App\Entity\UserTwoFactor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserTwoFactorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTwoFactor::class);
    }

    public function findAllWithUsers(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')->addSelect('u')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function countEnabledForRoles(array $roles): int
    {
        $qb = $this->createQueryBuilder('utf')
            ->select('COUNT(utf.id)')
            ->innerJoin('utf.user', 'u')
            ->andWhere('utf.enabled = :enabled')
            ->andWhere('utf.confirmedAt IS NOT NULL')
            ->setParameter('enabled', true);

        if (!empty($roles)) {
            $roleConditions = [];

            foreach ($roles as $i => $role) {
                $param = 'role_' . $i;
                $roleConditions[] = 'u.roles LIKE :' . $param;
                $qb->setParameter($param, '%"' . $role . '"%');
            }

            $qb->andWhere('(' . implode(' OR ', $roleConditions) . ')');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findEnabledUsersForRoles(array $roles): array
    {
        $qb = $this->createQueryBuilder('utf')
            ->addSelect('u')
            ->innerJoin('utf.user', 'u')
            ->andWhere('utf.enabled = :enabled')
            ->andWhere('utf.confirmedAt IS NOT NULL')
            ->setParameter('enabled', true);

        if (!empty($roles)) {
            $roleConditions = [];

            foreach ($roles as $i => $role) {
                $param = 'role_' . $i;
                $roleConditions[] = 'u.roles LIKE :' . $param;
                $qb->setParameter($param, '%"' . $role . '"%');
            }

            $qb->andWhere('(' . implode(' OR ', $roleConditions) . ')');
        }

        return $qb->getQuery()->getResult();
    }

    public function countEnabledForAnyUserRole(array $roles): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT u.id)')
            ->innerJoin('t.user', 'u')
            ->andWhere('t.enabled = true')
            ->andWhere('t.confirmedAt IS NOT NULL');

        $orX = $qb->expr()->orX();

        foreach ($roles as $index => $role) {
            $param = 'role_' . $index;
            $orX->add($qb->expr()->like('u.roles', ':' . $param));
            $qb->setParameter($param, '%"' . $role . '"%');
        }

        return (int) $qb
            ->andWhere($orX)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
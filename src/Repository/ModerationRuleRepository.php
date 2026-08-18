<?php

namespace App\Repository;

use App\Entity\ModerationRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ModerationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationRule::class);
    }

    public function findForIndex(string $q, string $category, string $severity, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('r.title LIKE :q OR r.description LIKE :q OR r.recommendedAction LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }
        if ($category !== '') {
            $qb->andWhere('r.category = :c')->setParameter('c', $category);
        }
        if ($severity !== '') {
            $qb->andWhere('r.severity = :s')->setParameter('s', $severity);
        }

        return $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countForIndex(string $q, string $category, string $severity): int
    {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)');

        if ($q !== '') {
            $qb->andWhere('r.title LIKE :q OR r.description LIKE :q OR r.recommendedAction LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }
        if ($category !== '') {
            $qb->andWhere('r.category = :c')->setParameter('c', $category);
        }
        if ($severity !== '') {
            $qb->andWhere('r.severity = :s')->setParameter('s', $severity);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function kpis(): array
    {
        $row = $this->createQueryBuilder('r')
            ->select("
              SUM(CASE WHEN r.isActive = true THEN 1 ELSE 0 END) as active,
              SUM(CASE WHEN r.isActive = false THEN 1 ELSE 0 END) as inactive,
              SUM(CASE WHEN r.severity = 'critical' THEN 1 ELSE 0 END) as critical,
              COUNT(r.id) as total
            ")
            ->getQuery()->getOneOrNullResult();

        return [
            'active' => (int)($row['active'] ?? 0),
            'inactive' => (int)($row['inactive'] ?? 0),
            'critical' => (int)($row['critical'] ?? 0),
            'total' => (int)($row['total'] ?? 0),
        ];
    }

    public function toggleActive(int $id, bool $active): void
    {
        $this->getEntityManager()->createQuery("
          UPDATE App\Entity\ModerationRule r
          SET r.isActive = :a, r.updatedAt = :dt
          WHERE r.id = :id
        ")
        ->setParameter('a', $active)
        ->setParameter('dt', new \DateTimeImmutable())
        ->setParameter('id', $id)
        ->execute();
    }
}
<?php

namespace App\Repository;

use App\Entity\Sanction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class SanctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sanction::class);
    }

    public function countActiveForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.user = :user')
            ->andWhere('s.supprimer = false')
            ->andWhere('s.status = :status')
            ->andWhere('(s.endsAt IS NULL OR s.endsAt >= :now)')
            ->setParameter('user', $user)
            ->setParameter('status', Sanction::STATUS_ACTIVE)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Sanction[]
     */
    public function findActiveForUser(User $user, int $limit = 3): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.supprimer = false')
            ->andWhere('s.status = :status')
            ->andWhere('(s.endsAt IS NULL OR s.endsAt >= :now)')
            ->setParameter('user', $user)
            ->setParameter('status', Sanction::STATUS_ACTIVE)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Sanction[]
     */
    public function findVisibleForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.supprimer = false')
            ->orderBy('s.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findForIndex(string $q, string $type, int $days, int $limit, int $offset): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $qb = $this->createQueryBuilder('s')
            ->select('s')
            ->leftJoin('s.targetUser', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('s.createdBy', 'm')->addSelect('m')
            ->andWhere('s.createdAt >= :from')->setParameter('from', $from)
            ->orderBy('s.createdAt', 'DESC');

        if ($type !== '' && $type !== 'all') {
            $qb->andWhere('s.type = :t')->setParameter('t', $type);
        }

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR pp.fullName LIKE :q OR s.reason LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        $qbCount = clone $qb;
        $total = (int)$qbCount
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT s.id)')
            ->setFirstResult(null)->setMaxResults(null)
            ->getQuery()->getSingleScalarResult();

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return ['items'=>$items,'total'=>$total];
    }

    public function stats(int $days = 30): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('s')
            ->select('s.createdAt, s.type')
            ->andWhere('s.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getArrayResult();

        $byType = [];
        $byDay = [];

        foreach ($rows as $r) {
            /** @var \DateTimeImmutable $dt */
            $dt = $r['createdAt'];
            $type = (string)$r['type'];

            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $day = $dt->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        }

        $labels = [];
        $values = [];
        for ($i=0; $i <= $days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $byDay[$d] ?? 0;
        }

        // top types -> array [{label,value}]
        arsort($byType);
        $topTypes = [];
        foreach ($byType as $k=>$v) $topTypes[] = ['label'=>$k,'value'=>$v];

        return [
            'daily' => ['labels'=>$labels, 'values'=>$values],
            'topTypes' => array_slice($topTypes, 0, 10),
            'total' => array_sum($values),
        ];
    }


    public function qbAdminList(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->leftJoin('s.createdBy', 'cb')->addSelect('cb');

        if (!empty($filters['q'])) {
            $qb->andWhere('u.email LIKE :q OR s.reason LIKE :q OR s.type LIKE :q OR s.status LIKE :q')
               ->setParameter('q', '%' . trim($filters['q']) . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('s.type = :type')->setParameter('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')->setParameter('status', $filters['status']);
        }

        if ($filters['hidden'] ?? '' !== '') {
            $qb->andWhere('s.supprimer = :hidden')->setParameter('hidden', (bool) $filters['hidden']);
        }

        return $qb->orderBy('s.createdAt', 'DESC');
    }

    public function countAllAdmin(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
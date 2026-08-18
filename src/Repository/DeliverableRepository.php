<?php

namespace App\Repository;

use App\Entity\Deliverable;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DeliverableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deliverable::class);
    }

    /** Liste paginable + filtres simples */
    public function findForUser(User $user, ?string $status = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.owner = :u')->setParameter('u', $user)
            ->orderBy('d.dueAt', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status) {
            $qb->andWhere('d.status = :s')->setParameter('s', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /** KPIs */
    public function countByStatusForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.status as status, COUNT(d.id) as c')
            ->andWhere('d.owner = :u')->setParameter('u', $user)
            ->groupBy('d.status')
            ->getQuery()->getArrayResult();

        $out = [
            Deliverable::STATUS_TODO => 0,
            Deliverable::STATUS_SUBMITTED => 0,
            Deliverable::STATUS_VALIDATED => 0,
            Deliverable::STATUS_REJECTED => 0,
        ];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        $out['total'] = array_sum($out);

        return $out;
    }
}
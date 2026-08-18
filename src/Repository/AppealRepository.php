<?php
// src/Repository/AppealRepository.php

namespace App\Repository;

use App\Entity\Appeal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appeal::class);
    }

    /**
     * ✅ Liste (anti N+1) : appeal + user + pp + pro + decidedBy
     */
    public function findForIndex(string $q, string $status, string $role, int $days, int $limit, int $offset): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->leftJoin('a.decidedBy', 'm')->addSelect('m')
            ->andWhere('a.createdAt >= :from')->setParameter('from', $from)
            ->orderBy('a.createdAt', 'DESC');

        if ($status !== '' && $status !== 'all') {
            $qb->andWhere('a.status = :st')->setParameter('st', $status);
        }

        if ($role === 'talent') {
            $qb->andWhere('u.roles LIKE :rt')->setParameter('rt', '%"ROLE_TALENT"%');
        } elseif ($role === 'particulier') {
            $qb->andWhere('u.roles LIKE :rp')->setParameter('rp', '%"ROLE_PARTICULIER"%');
        } elseif ($role === 'company') {
            $qb->andWhere('u.roles LIKE :rc')->setParameter('rc', '%"ROLE_COMPANY"%');
        }

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.phone LIKE :q OR pp.fullName LIKE :q OR a.reason LIKE :q OR a.moderatorNote LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $total = (int) (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT a.id)')
            ->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * ✅ Stats pour charts (daily + status + role) calculées en PHP (safe)
     */
    public function stats(int $days = 30): array
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        $rows = $this->createQueryBuilder('a')
            ->select('a.createdAt, a.status, u.roles')
            ->join('a.user', 'u')
            ->andWhere('a.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getArrayResult();

        $byStatus = ['pending'=>0,'accepted'=>0,'rejected'=>0];
        $byRole   = ['talent'=>0,'particulier'=>0,'company'=>0];
        $byDayMap = [];

        foreach ($rows as $r) {
            /** @var \DateTimeImmutable $dt */
            $dt = $r['createdAt'];
            $st = (string) $r['status'];
            $roles = $r['roles'] ?? [];

            $day = $dt->format('Y-m-d');
            $byDayMap[$day] = ($byDayMap[$day] ?? 0) + 1;

            if (isset($byStatus[$st])) $byStatus[$st]++;

            // rôle principal
            $main = 'particulier';
            if (is_array($roles) && in_array('ROLE_TALENT', $roles, true)) $main = 'talent';
            elseif (is_array($roles) && in_array('ROLE_COMPANY', $roles, true)) $main = 'company';
            elseif (is_array($roles) && in_array('ROLE_PARTICULIER', $roles, true)) $main = 'particulier';
            $byRole[$main]++;
        }

        $labels = [];
        $values = [];
        for ($i=0; $i <= $days; $i++) {
            $d = $from->modify("+{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $values[] = $byDayMap[$d] ?? 0;
        }

        return [
            'daily' => ['labels'=>$labels,'values'=>$values],
            'byStatus' => $byStatus,
            'byRole' => $byRole,
            'total' => array_sum($values),
        ];
    }

    public function countAppeals(int $days = 30): int
    {
        $from = (new \DateTimeImmutable())->modify("-{$days} days")->setTime(0,0,0);

        return (int)$this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.createdAt >= :from')->setParameter('from', $from)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * ✅ Détail : appeal + user + pp + pro + decidedBy
     */
    public function findOneWithUserProfiles(int $id): ?Appeal
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->leftJoin('a.decidedBy', 'm')->addSelect('m')
            ->andWhere('a.id = :id')->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ✅ Mise à jour DQL : accepter / rejeter (sans recharger l’entité)
     */
    public function markDecision(int $appealId, string $status, int $moderatorId, ?string $note = null): void
    {
        $status = in_array($status, [Appeal::STATUS_ACCEPTED, Appeal::STATUS_REJECTED], true)
            ? $status
            : Appeal::STATUS_PENDING;

        $em = $this->getEntityManager();
        $modRef = $em->getReference(User::class, $moderatorId);

        $em->createQuery('
            UPDATE App\Entity\Appeal a
            SET a.status = :st,
                a.decidedAt = :dt,
                a.decidedBy = :mod,
                a.moderatorNote = :note
            WHERE a.id = :id
        ')
        ->setParameter('st', $status)
        ->setParameter('dt', new \DateTimeImmutable())
        ->setParameter('mod', $modRef)
        ->setParameter('note', $note)
        ->setParameter('id', $appealId)
        ->execute();
    }

    public function updateNote(int $appealId, ?string $note): void
    {
        $this->getEntityManager()->createQuery('
            UPDATE App\Entity\Appeal a
            SET a.moderatorNote = :note
            WHERE a.id = :id
        ')
        ->setParameter('note', $note)
        ->setParameter('id', $appealId)
        ->execute();
    }
}
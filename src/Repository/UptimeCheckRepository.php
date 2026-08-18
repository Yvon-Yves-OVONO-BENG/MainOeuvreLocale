<?php

namespace App\Repository;

use App\Entity\UptimeCheck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UptimeCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UptimeCheck::class);
    }

    public function getUptimePercentLastHours(int $hours, ?string $serviceName = null): float
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $totalQb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.checkedAt >= :since')
            ->setParameter('since', $since);

        $upQb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.checkedAt >= :since')
            ->andWhere('u.isUp = :up')
            ->setParameter('since', $since)
            ->setParameter('up', true);

        if ($serviceName) {
            $totalQb->andWhere('u.serviceName = :serviceName')
                ->setParameter('serviceName', $serviceName);

            $upQb->andWhere('u.serviceName = :serviceName')
                ->setParameter('serviceName', $serviceName);
        }

        $total = (int) $totalQb->getQuery()->getSingleScalarResult();
        $up = (int) $upQb->getQuery()->getSingleScalarResult();

        if ($total === 0) {
            return 0.0;
        }

        return round(($up / $total) * 100, 2);
    }

    public function getUptimePercentLastDays(int $days, ?string $serviceName = null): float
    {
        return $this->getUptimePercentLastHours($days * 24, $serviceName);
    }

    public function getAverageLatencyLastHours(int $hours, ?string $serviceName = null): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $qb = $this->createQueryBuilder('u')
            ->select('AVG(u.responseTimeMs) as avg_latency')
            ->andWhere('u.checkedAt >= :since')
            ->andWhere('u.isUp = :up')
            ->andWhere('u.responseTimeMs IS NOT NULL')
            ->setParameter('since', $since)
            ->setParameter('up', true);

        if ($serviceName) {
            $qb->andWhere('u.serviceName = :serviceName')
                ->setParameter('serviceName', $serviceName);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) round((float) ($result ?? 0));
    }

    public function countFailuresLastHours(int $hours, ?string $serviceName = null): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.checkedAt >= :since')
            ->andWhere('u.isUp = :up')
            ->setParameter('since', $since)
            ->setParameter('up', false);

        if ($serviceName) {
            $qb->andWhere('u.serviceName = :serviceName')
                ->setParameter('serviceName', $serviceName);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findLatestChecks(int $limit = 20, ?string $serviceName = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.checkedAt', 'DESC')
            ->setMaxResults($limit);

        if ($serviceName) {
            $qb->andWhere('u.serviceName = :serviceName')
                ->setParameter('serviceName', $serviceName);
        }

        return $qb->getQuery()->getResult();
    }
}
<?php

namespace App\Repository;

use App\Entity\ApiEndpointMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApiEndpointMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiEndpointMetric::class);
    }

    public function getTopEndpoints(int $days = 30, int $limit = 3): array
    {
        $startDate = (new \DateTimeImmutable('today'))
            ->modify('-' . max(0, $days - 1) . ' days');

        $rows = $this->createQueryBuilder('a')
            ->select('a.routePath AS endpoint')
            ->addSelect('SUM(a.hits) AS hits')
            ->addSelect('SUM(a.errorHits) AS errors')
            ->addSelect('(SUM(a.totalDurationMs) / NULLIF(SUM(a.hits), 0)) AS avgMs')
            ->andWhere('a.metricDate >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('a.routePath')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            $hits = (int) ($row['hits'] ?? 0);
            $errors = (int) ($row['errors'] ?? 0);

            return [
                'endpoint' => $row['endpoint'] ?? '—',
                'hits' => $hits,
                'errors' => $errors,
                'avgMs' => round((float) ($row['avgMs'] ?? 0), 2),
                'errorRate' => $hits > 0 ? round(($errors * 100) / $hits, 1) : 0,
            ];
        }, $rows);
    }

    public function getTopEndpointsForDay(\DateTimeInterface $day, int $limit = 3): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.routePath AS endpoint')
            ->addSelect('SUM(a.hits) AS hits')
            ->addSelect('SUM(a.errorHits) AS errors')
            ->addSelect('(SUM(a.totalDurationMs) / NULLIF(SUM(a.hits), 0)) AS avgMs')
            ->andWhere('a.metricDate = :metricDate')
            ->setParameter('metricDate', $day)
            ->groupBy('a.routePath')
            ->orderBy('hits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            $hits = (int) ($row['hits'] ?? 0);
            $errors = (int) ($row['errors'] ?? 0);

            return [
                'endpoint' => $row['endpoint'] ?? '—',
                'hits' => $hits,
                'errors' => $errors,
                'avgMs' => round((float) ($row['avgMs'] ?? 0), 2),
                'errorRate' => $hits > 0 ? round(($errors * 100) / $hits, 1) : 0,
            ];
        }, $rows);
    }


    public function findLatestMetricsList(int $days = 30, int $limit = 100): array
    {
        $startDate = (new \DateTimeImmutable('today'))
            ->modify('-' . max(0, $days - 1) . ' days');

        return $this->createQueryBuilder('a')
            ->andWhere('a.metricDate >= :startDate')
            ->setParameter('startDate', $startDate)
            ->orderBy('a.metricDate', 'DESC')
            ->addOrderBy('a.hits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
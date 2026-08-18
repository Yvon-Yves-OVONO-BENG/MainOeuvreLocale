<?php

namespace App\Repository;

use App\Entity\ApiLatencyLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApiLatencyLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiLatencyLog::class);
    }

    public function getAverageLatencyLastHours(int $hours): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $result = $this->createQueryBuilder('l')
            ->select('AVG(l.responseTimeMs) AS avg_latency')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) round((float) ($result ?? 0));
    }

    public function getMaxLatencyLastHours(int $hours): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $result = $this->createQueryBuilder('l')
            ->select('MAX(l.responseTimeMs) AS max_latency')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function countSlowRequestsLastHours(int $hours, int $thresholdMs = 500): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt >= :since')
            ->andWhere('l.responseTimeMs >= :threshold')
            ->setParameter('since', $since)
            ->setParameter('threshold', $thresholdMs)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countRequestsLastHours(int $hours): int
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestLogs(int $limit = 30): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLatencySeriesLastDays(int $days = 7): array
    {
        $start = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', $days - 1))
            ->setTime(0, 0, 0);

        $sql = <<<SQL
            SELECT 
                DATE(created_at) AS dte,
                ROUND(AVG(response_time_ms), 0) AS avg_latency
            FROM api_latency_log
            WHERE created_at >= :start
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql, [
                'start' => $start->format('Y-m-d H:i:s'),
            ])
            ->fetchAllAssociative();
    }

    public function getTopSlowEndpointsLastHours(int $hours = 24, int $limit = 10): array
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d hours', $hours));

        $sql = <<<SQL
            SELECT
                path,
                method,
                COUNT(id) AS hits,
                ROUND(AVG(response_time_ms), 0) AS avg_latency,
                MAX(response_time_ms) AS max_latency
            FROM api_latency_log
            WHERE created_at >= :since
            GROUP BY path, method
            ORDER BY avg_latency DESC, hits DESC
            LIMIT :limit
        SQL;

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                $sql,
                [
                    'since' => $since->format('Y-m-d H:i:s'),
                    'limit' => $limit,
                ],
                [
                    'limit' => \PDO::PARAM_INT,
                ]
            )
            ->fetchAllAssociative();
    }
}
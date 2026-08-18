<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ApiMetricsReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getTopEndpoints(int $days = 30, int $limit = 3): array
    {
        $limit = max(1, $limit);
        $startDate = (new \DateTimeImmutable('today'))
            ->modify('-' . ($days - 1) . ' days')
            ->format('Y-m-d');

        $sql = "
            SELECT
                route_path AS endpoint,
                SUM(hits) AS hits,
                SUM(error_hits) AS errors,
                ROUND(SUM(total_duration_ms) / NULLIF(SUM(hits), 0), 2) AS avg_ms
            FROM api_endpoint_metric
            WHERE metric_date >= :start_date
            GROUP BY route_path
            ORDER BY hits DESC
            LIMIT {$limit}
        ";

        $rows = $this->connection->fetchAllAssociative($sql, [
            'start_date' => $startDate,
        ]);

        return array_map(static function (array $row): array {
            $hits = (int) $row['hits'];
            $errors = (int) $row['errors'];

            return [
                'endpoint' => $row['endpoint'],
                'hits' => $hits,
                'errors' => $errors,
                'avgMs' => (float) ($row['avg_ms'] ?? 0),
                'errorRate' => $hits > 0 ? round(($errors * 100) / $hits, 1) : 0,
            ];
        }, $rows);
    }
}
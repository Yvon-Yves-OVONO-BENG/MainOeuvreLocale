<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiMetricsRecorder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function record(Request $request, Response $response, float $durationMs): void
    {
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $method = strtoupper($request->getMethod());
        $statusCode = $response->getStatusCode();
        $errorHits = $statusCode >= 400 ? 1 : 0;
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = "
            INSERT INTO api_endpoint_metric (
                route_path,
                http_method,
                metric_date,
                hits,
                error_hits,
                total_duration_ms,
                last_status_code,
                last_called_at
            )
            VALUES (
                :route_path,
                :http_method,
                :metric_date,
                1,
                :error_hits,
                :total_duration_ms,
                :last_status_code,
                :last_called_at
            )
            ON DUPLICATE KEY UPDATE
                hits = hits + 1,
                error_hits = error_hits + VALUES(error_hits),
                total_duration_ms = total_duration_ms + VALUES(total_duration_ms),
                last_status_code = VALUES(last_status_code),
                last_called_at = VALUES(last_called_at)
        ";

        $this->connection->executeStatement($sql, [
            'route_path' => $path,
            'http_method' => $method,
            'metric_date' => $today,
            'error_hits' => $errorHits,
            'total_duration_ms' => round($durationMs, 2),
            'last_status_code' => $statusCode,
            'last_called_at' => $now,
        ]);
    }

}
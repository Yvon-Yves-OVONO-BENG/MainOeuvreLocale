<?php

namespace App\Entity;

use App\Repository\ApiEndpointMetricRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiEndpointMetricRepository::class)]
#[ORM\Table(name: 'api_endpoint_metric')]
#[ORM\UniqueConstraint(name: 'uq_api_endpoint_metric', columns: ['route_path', 'http_method', 'metric_date'])]
#[ORM\Index(name: 'idx_api_metric_date', columns: ['metric_date'])]
#[ORM\Index(name: 'idx_api_metric_hits', columns: ['hits'])]
#[ORM\Index(name: 'idx_api_metric_route', columns: ['route_path'])]
class ApiEndpointMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'route_path', length: 255)]
    private ?string $routePath = null;

    #[ORM\Column(name: 'http_method', length: 10)]
    private ?string $httpMethod = null;

    #[ORM\Column(name: 'metric_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $metricDate = null;

    #[ORM\Column]
    private int $hits = 0;

    #[ORM\Column(name: 'error_hits')]
    private int $errorHits = 0;

    #[ORM\Column(name: 'total_duration_ms', type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalDurationMs = '0.00';

    #[ORM\Column(name: 'last_status_code', nullable: true)]
    private ?int $lastStatusCode = null;

    #[ORM\Column(name: 'last_called_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastCalledAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoutePath(): ?string
    {
        return $this->routePath;
    }

    public function setRoutePath(string $routePath): static
    {
        $this->routePath = $routePath;
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): static
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getMetricDate(): ?\DateTimeInterface
    {
        return $this->metricDate;
    }

    public function setMetricDate(\DateTimeInterface $metricDate): static
    {
        $this->metricDate = $metricDate;
        return $this;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function setHits(int $hits): static
    {
        $this->hits = $hits;
        return $this;
    }

    public function incrementHits(int $by = 1): static
    {
        $this->hits += $by;
        return $this;
    }

    public function getErrorHits(): int
    {
        return $this->errorHits;
    }

    public function setErrorHits(int $errorHits): static
    {
        $this->errorHits = $errorHits;
        return $this;
    }

    public function incrementErrorHits(int $by = 1): static
    {
        $this->errorHits += $by;
        return $this;
    }

    public function getTotalDurationMs(): string
    {
        return $this->totalDurationMs;
    }

    public function setTotalDurationMs(string $totalDurationMs): static
    {
        $this->totalDurationMs = $totalDurationMs;
        return $this;
    }

    public function addDurationMs(string $durationMs): static
    {
        $this->totalDurationMs = bcadd($this->totalDurationMs, $durationMs, 2);
        return $this;
    }

    public function getLastStatusCode(): ?int
    {
        return $this->lastStatusCode;
    }

    public function setLastStatusCode(?int $lastStatusCode): static
    {
        $this->lastStatusCode = $lastStatusCode;
        return $this;
    }

    public function getLastCalledAt(): ?\DateTimeInterface
    {
        return $this->lastCalledAt;
    }

    public function setLastCalledAt(\DateTimeInterface $lastCalledAt): static
    {
        $this->lastCalledAt = $lastCalledAt;
        return $this;
    }

    public function getAverageDurationMs(): float
    {
        if ($this->hits <= 0) {
            return 0.0;
        }

        return (float) $this->totalDurationMs / $this->hits;
    }
}
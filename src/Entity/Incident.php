<?php

namespace App\Entity;

use App\Repository\IncidentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Incident technique capturé automatiquement avant l'affichage de la page
 * d'erreur publique. Cette table est volontairement distincte des incidents
 * de sécurité : elle sert au diagnostic applicatif (HTTP / Symfony / PHP).
 */
#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'incident')]
#[ORM\Index(columns: ['status', 'last_occurred_at'], name: 'idx_incident_status_last')]
#[ORM\Index(columns: ['http_status', 'last_occurred_at'], name: 'idx_incident_http_last')]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_incident_fingerprint')]
class Incident
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $reference = '';

    #[ORM\Column(length: 64)]
    private string $fingerprint = '';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(length: 20)]
    private string $severity = self::SEVERITY_ERROR;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\Column(length: 255)]
    private string $exceptionClass = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $requestUri = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sourceFile = null;

    #[ORM\Column(nullable: true)]
    private ?int $sourceLine = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $trace = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $referer = null;

    #[ORM\Column]
    private int $occurrenceCount = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastOccurredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastOccurredAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): self { $this->reference = $reference; return $this; }
    public function getFingerprint(): string { return $this->fingerprint; }
    public function setFingerprint(string $fingerprint): self { $this->fingerprint = $fingerprint; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $severity): self { $this->severity = $severity; return $this; }
    public function getHttpStatus(): ?int { return $this->httpStatus; }
    public function setHttpStatus(?int $httpStatus): self { $this->httpStatus = $httpStatus; return $this; }
    public function getExceptionClass(): string { return $this->exceptionClass; }
    public function setExceptionClass(string $exceptionClass): self { $this->exceptionClass = $exceptionClass; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): self { $this->message = $message; return $this; }
    public function getRoute(): ?string { return $this->route; }
    public function setRoute(?string $route): self { $this->route = $route; return $this; }
    public function getRequestUri(): ?string { return $this->requestUri; }
    public function setRequestUri(?string $requestUri): self { $this->requestUri = $requestUri; return $this; }
    public function getMethod(): ?string { return $this->method; }
    public function setMethod(?string $method): self { $this->method = $method; return $this; }
    public function getSourceFile(): ?string { return $this->sourceFile; }
    public function setSourceFile(?string $sourceFile): self { $this->sourceFile = $sourceFile; return $this; }
    public function getSourceLine(): ?int { return $this->sourceLine; }
    public function setSourceLine(?int $sourceLine): self { $this->sourceLine = $sourceLine; return $this; }
    public function getTrace(): ?array { return $this->trace; }
    public function setTrace(?array $trace): self { $this->trace = $trace; return $this; }
    public function getContext(): ?array { return $this->context; }
    public function setContext(?array $context): self { $this->context = $context; return $this; }
    public function getIp(): ?string { return $this->ip; }
    public function setIp(?string $ip): self { $this->ip = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }
    public function getReferer(): ?string { return $this->referer; }
    public function setReferer(?string $referer): self { $this->referer = $referer; return $this; }
    public function getOccurrenceCount(): int { return $this->occurrenceCount; }
    public function setOccurrenceCount(int $occurrenceCount): self { $this->occurrenceCount = max(1, $occurrenceCount); return $this; }
    public function incrementOccurrenceCount(): self { ++$this->occurrenceCount; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastOccurredAt(): \DateTimeImmutable { return $this->lastOccurredAt; }
    public function setLastOccurredAt(\DateTimeImmutable $lastOccurredAt): self { $this->lastOccurredAt = $lastOccurredAt; return $this; }
    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function resolve(): self { $this->status = self::STATUS_RESOLVED; $this->resolvedAt = new \DateTimeImmutable(); return $this; }
}

<?php

namespace App\Entity;

use App\Repository\WebhookDeliveryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookDeliveryRepository::class)]
#[ORM\Table(name: 'webhook_delivery')]
class WebhookDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WebhookEndpoint::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WebhookEndpoint $endpoint = null;

    #[ORM\Column(length: 120)]
    private ?string $eventName = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $eventReference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $payloadHash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $signatureValid = false;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $attemptCount = 1;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseBody = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEndpoint(): ?WebhookEndpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?WebhookEndpoint $endpoint): static
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): static
    {
        $this->eventName = $eventName;
        return $this;
    }

    public function getEventReference(): ?string
    {
        return $this->eventReference;
    }

    public function setEventReference(?string $eventReference): static
    {
        $this->eventReference = $eventReference;
        return $this;
    }

    public function getPayloadHash(): ?string
    {
        return $this->payloadHash;
    }

    public function setPayloadHash(?string $payloadHash): static
    {
        $this->payloadHash = $payloadHash;
        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): static
    {
        $this->signature = $signature;
        return $this;
    }

    public function isSignatureValid(): bool
    {
        return $this->signatureValid;
    }

    public function setSignatureValid(bool $signatureValid): static
    {
        $this->signatureValid = $signatureValid;
        return $this;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(?int $httpStatus): static
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function setAttemptCount(int $attemptCount): static
    {
        $this->attemptCount = $attemptCount;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function setResponseBody(?string $responseBody): static
    {
        $this->responseBody = $responseBody;
        return $this;
    }
}
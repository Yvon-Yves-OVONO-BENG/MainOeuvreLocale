<?php

namespace App\Entity;

use App\Repository\CallSignalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Signal WebRTC transporté par AJAX (offer / answer / ICE), sans Mercure. */
#[ORM\Entity(repositoryClass: CallSignalRepository::class)]
#[ORM\Table(name: 'call_signal')]
#[ORM\Index(columns: ['call_session_id', 'id'], name: 'idx_call_signal_call_id')]
class CallSignal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CallSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CallSession $callSession = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\Column(length: 20)]
    private string $type = '';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $payload = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getCallSession(): ?CallSession { return $this->callSession; }
    public function setCallSession(CallSession $callSession): self { $this->callSession = $callSession; return $this; }
    public function getSender(): ?User { return $this->sender; }
    public function setSender(User $sender): self { $this->sender = $sender; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getPayload(): mixed { return $this->payload; }
    public function setPayload(mixed $payload): self { $this->payload = $payload; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

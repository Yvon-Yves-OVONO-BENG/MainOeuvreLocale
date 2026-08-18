<?php

namespace App\Entity;

use App\Repository\CallSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CallSessionRepository::class)]
#[ORM\Table(name: 'call_session')]
class CallSession
{
    public const TYPE_AUDIO = 'audio';
    public const TYPE_VIDEO = 'video';

    public const STATUS_RINGING  = 'ringing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_ENDED    = 'ended';
    public const STATUS_MISSED   = 'missed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $caller = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $callee = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_AUDIO;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_RINGING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $answeredAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $conversation): self { $this->conversation = $conversation; return $this; }

    public function getCaller(): ?User { return $this->caller; }
    public function setCaller(?User $caller): self { $this->caller = $caller; return $this; }

    public function getCallee(): ?User { return $this->callee; }
    public function setCallee(?User $callee): self { $this->callee = $callee; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }

    public function getAnsweredAt(): ?\DateTimeImmutable { return $this->answeredAt; }
    public function setAnsweredAt(?\DateTimeImmutable $answeredAt): self { $this->answeredAt = $answeredAt; return $this; }

    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(?\DateTimeImmutable $endedAt): self { $this->endedAt = $endedAt; return $this; }

    public function getMeta(): ?array { return $this->meta; }
    public function setMeta(?array $meta): self { $this->meta = $meta; return $this; }

    public function accept(): self
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->answeredAt = new \DateTimeImmutable();
        return $this;
    }

    public function decline(): self
    {
        $this->status = self::STATUS_DECLINED;
        $this->endedAt = new \DateTimeImmutable();
        return $this;
    }

    public function end(): self
    {
        $this->status = self::STATUS_ENDED;
        $this->endedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isParticipant(User $user): bool
    {
        return $this->caller?->getId() === $user->getId()
            || $this->callee?->getId() === $user->getId();
    }

    public function getOther(User $me): ?User
    {
        if ($this->caller?->getId() === $me->getId()) {
            return $this->callee;
        }

        if ($this->callee?->getId() === $me->getId()) {
            return $this->caller;
        }

        return null;
    }
}
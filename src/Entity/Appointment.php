<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'appointment')]
#[ORM\Index(name: 'idx_appointment_start', columns: ['start_at'])]
#[ORM\Index(name: 'idx_appointment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_appointment_particular', columns: ['particular_id'])]
#[ORM\Index(name: 'idx_appointment_talent', columns: ['talent_id'])]
class Appointment
{
    public const STATUS_PROPOSED  = 'proposed';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REFUSED   = 'refused';
    public const STATUS_CANCELED  = 'canceled';
    public const STATUS_DONE      = 'done';

    public const TYPE_INTERVIEW = 'interview';
    public const TYPE_CALL      = 'call';
    public const TYPE_MEETING   = 'meeting';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Le particulier (propriétaire de la mission / recruteur)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $particular = null;

    // Le talent convoqué
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $talent = null;

    // Mission concernée
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Job $job = null;

    // Candidature d’origine (drag du candidat depuis la mission)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Application $application = null;

    // Conversation liée (pour message auto)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Conversation $conversation = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PROPOSED;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_INTERVIEW;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $endAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $refusedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $canceledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $canceledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    // Flags de rappels pour éviter les doublons
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderJ1SentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderH1SentAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->startAt = $now;
        $this->endAt = $now->modify('+30 minutes');
    }

    public function getId(): ?int { return $this->id; }

    public function getParticular(): ?User { return $this->particular; }
    public function setParticular(?User $particular): static { $this->particular = $particular; return $this; }

    public function getTalent(): ?User { return $this->talent; }
    public function setTalent(?User $talent): static { $this->talent = $talent; return $this; }

    public function getJob(): ?Job { return $this->job; }
    public function setJob(?Job $job): static { $this->job = $job; return $this; }

    public function getApplication(): ?Application { return $this->application; }
    public function setApplication(?Application $application): static { $this->application = $application; return $this; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $conversation): static { $this->conversation = $conversation; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getStartAt(): \DateTimeImmutable { return $this->startAt; }
    public function setStartAt(\DateTimeImmutable $startAt): static { $this->startAt = $startAt; return $this; }

    public function getEndAt(): \DateTimeImmutable { return $this->endAt; }
    public function setEndAt(\DateTimeImmutable $endAt): static { $this->endAt = $endAt; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): static { $this->location = $location; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static { $this->confirmedAt = $confirmedAt; return $this; }

    public function getRefusedAt(): ?\DateTimeImmutable { return $this->refusedAt; }
    public function setRefusedAt(?\DateTimeImmutable $refusedAt): static { $this->refusedAt = $refusedAt; return $this; }

    public function getCanceledAt(): ?\DateTimeImmutable { return $this->canceledAt; }
    public function setCanceledAt(?\DateTimeImmutable $canceledAt): static { $this->canceledAt = $canceledAt; return $this; }

    public function getDoneAt(): ?\DateTimeImmutable { return $this->doneAt; }
    public function setDoneAt(?\DateTimeImmutable $doneAt): static { $this->doneAt = $doneAt; return $this; }

    public function getCanceledBy(): ?User { return $this->canceledBy; }
    public function setCanceledBy(?User $canceledBy): static { $this->canceledBy = $canceledBy; return $this; }

    public function getCancelReason(): ?string { return $this->cancelReason; }
    public function setCancelReason(?string $cancelReason): static { $this->cancelReason = $cancelReason; return $this; }

    public function getReminderJ1SentAt(): ?\DateTimeImmutable { return $this->reminderJ1SentAt; }
    public function setReminderJ1SentAt(?\DateTimeImmutable $dt): static { $this->reminderJ1SentAt = $dt; return $this; }

    public function getReminderH1SentAt(): ?\DateTimeImmutable { return $this->reminderH1SentAt; }
    public function setReminderH1SentAt(?\DateTimeImmutable $dt): static { $this->reminderH1SentAt = $dt; return $this; }

    public function getMeta(): ?array { return $this->meta; }
    public function setMeta(?array $meta): static { $this->meta = $meta; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    public function getDurationMinutes(): int
    {
        return (int) max(1, ($this->endAt->getTimestamp() - $this->startAt->getTimestamp()) / 60);
    }

    public function canBeMoved(): bool
    {
        return \in_array($this->status, [self::STATUS_PROPOSED, self::STATUS_CONFIRMED], true);
    }

    public function isCanceledOrRefused(): bool
    {
        return \in_array($this->status, [self::STATUS_CANCELED, self::STATUS_REFUSED], true);
    }
}
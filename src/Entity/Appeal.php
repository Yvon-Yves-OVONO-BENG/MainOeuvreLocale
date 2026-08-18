<?php
// src/Entity/Appeal.php

namespace App\Entity;

use App\Repository\AppealRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppealRepository::class)]
#[ORM\Table(name: 'appeal')]
#[ORM\Index(name: 'idx_appeal_status', columns: ['status'])]
#[ORM\Index(name: 'idx_appeal_created_at', columns: ['created_at'])]
class Appeal
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    // celui qui conteste
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // Motif de contestation (texte libre)
    #[ORM\Column(type: 'text')]
    private string $reason = '';

    // Note interne modérateur (optionnel)
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderatorNote = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): self { $this->reason = $reason; return $this; }

    public function getModeratorNote(): ?string { return $this->moderatorNote; }
    public function setModeratorNote(?string $note): self { $this->moderatorNote = $note; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function setDecidedAt(?\DateTimeImmutable $d): self { $this->decidedAt = $d; return $this; }

    public function getDecidedBy(): ?User { return $this->decidedBy; }
    public function setDecidedBy(?User $u): self { $this->decidedBy = $u; return $this; }
}
<?php
// src/Entity/Sanction.php

namespace App\Entity;

use App\Repository\SanctionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SanctionRepository::class)]
#[ORM\Table(name: 'sanction')]
#[ORM\Index(name: 'idx_sanction_status', columns: ['status'])]
#[ORM\Index(name: 'idx_sanction_type', columns: ['type'])]
#[ORM\Index(name: 'idx_sanction_created_at', columns: ['created_at'])]
class Sanction
{
    public const TYPE_WARNING   = 'warning';
    public const TYPE_SUSPEND   = 'suspend';
    public const TYPE_BAN       = 'ban';
    public const TYPE_RESTRICT  = 'restrict';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELED  = 'canceled';
    public const STATUS_REVOKED = 'revoked';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Utilisateur sanctionné
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Admin / modérateur qui crée la sanction
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /**
     * Type de sanction
     * Ex: warning, suspend, ban, restrict
     */
    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_WARNING;

    /**
     * État actuel
     * Ex: active, expired, canceled
     */
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    /**
     * Motif principal
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $reason = '';

    /**
     * Notes internes admin
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    /**
     * Date de création
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Début d'effet
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    /**
     * Fin d'effet pour une sanction temporaire
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /**
     * Date d'annulation si annulée
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $canceledAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $supprimer = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->startsAt = new \DateTimeImmutable();
        $this->status = self::STATUS_ACTIVE;
        $this->type = self::TYPE_WARNING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): self
    {
        $this->adminNote = $adminNote;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;
        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;
        return $this;
    }

    public function getCanceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeImmutable $canceledAt): self
    {
        $this->canceledAt = $canceledAt;
        return $this;
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->endsAt !== null && $this->endsAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->endsAt !== null && $this->endsAt < new \DateTimeImmutable();
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELED;
        $this->canceledAt = new \DateTimeImmutable();
        return $this;
    }

    public function expire(): self
    {
        $this->status = self::STATUS_EXPIRED;
        return $this;
    }

    public function isPermanent(): bool
    {
        return $this->type === self::TYPE_BAN && $this->endsAt === null;
    }

    public function isTemporary(): bool
    {
        return $this->endsAt !== null;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function isSupprimer(): bool
    {
        return $this->supprimer;
    }

    public function setSupprimer(bool $supprimer): self
    {
        $this->supprimer = $supprimer;
        return $this;
    }

    public function revoke(): self
    {
        $this->status = self::STATUS_REVOKED;
        return $this;
    }
}
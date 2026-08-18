<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'report')]
#[ORM\Index(name: 'idx_report_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_report_status', columns: ['status'])]
#[ORM\Index(name: 'idx_report_target', columns: ['target_user_id'])]
class Report
{
    public const STATUS_OPEN     = 'open';     // signalement actif
    public const STATUS_CANCELED = 'canceled'; // annulé par le reporter
    public const STATUS_RESOLVED = 'resolved'; // traité par admin

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * ✅ Celui qui signale (reporter)
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reporter = null;

    /**
     * ✅ Compte signalé (target)
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $targetUser = null;

    /**
     * ✅ Description: pourquoi je signale ce compte
     * Ex: "Il publie des jobs frauduleux..., spam..., usurpation..."
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $reason = '';

    /**
     * ✅ Etat du signalement
     */
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    /**
     * ✅ Admin / Modérateur qui traite (optionnel)
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $handledBy = null;

    /**
     * ✅ Date de création du signalement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * ✅ Date de traitement (si resolu)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    /**
     * ✅ Date d’annulation (si canceled)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $canceledAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $supprimer = false;

    #[ORM\ManyToOne(inversedBy: 'reports')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CategorieReport $categorie = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_OPEN;
    }

    // -------------------------
    // Getters / Setters
    // -------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): self
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): self
    {
        $this->targetUser = $targetUser;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getHandledBy(): ?User
    {
        return $this->handledBy;
    }

    public function setHandledBy(?User $handledBy): self
    {
        $this->handledBy = $handledBy;
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

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function setHandledAt(?\DateTimeImmutable $handledAt): self
    {
        $this->handledAt = $handledAt;
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

    // -------------------------
    // Helpers métier
    // -------------------------

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELED;
        $this->canceledAt = new \DateTimeImmutable();
        return $this;
    }

    public function resolve(?User $admin = null): self
    {
        $this->status = self::STATUS_RESOLVED;
        $this->handledAt = new \DateTimeImmutable();
        if ($admin) $this->handledBy = $admin;
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

    public function getCategorie(): ?CategorieReport
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieReport $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }


}

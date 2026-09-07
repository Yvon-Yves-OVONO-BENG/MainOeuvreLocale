<?php

namespace App\Entity;

use App\Repository\ApplicationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'application')]
#[ORM\UniqueConstraint(name: 'uniq_application_user_job', columns: ['user_id', 'job_id'])]
class Application
{
    public const STATUS_NEW       = 'NEW';
    public const STATUS_REVIEWED  = 'REVIEWED';
    public const STATUS_SHORTLIST = 'SHORTLIST';
    public const STATUS_REJECTED  = 'REJECTED';
    public const STATUS_ACCEPTED  = 'ACCEPTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ===== jobId =====
    #[ORM\ManyToOne(targetEntity: Job::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Job $job = null;

    // ===== userId =====
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // ===== status =====
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_NEW;

    // ===== createdAt =====
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $viewedAt = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $internalScore = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNote = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $internallyRatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_NEW;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getViewedAt(): ?\DateTimeInterface { return $this->viewedAt; }
    public function setViewedAt(?\DateTimeInterface $dt): static { $this->viewedAt = $dt; return $this; }
    public function isViewed(): bool { return $this->viewedAt !== null; }
    public function getInternalScore(): ?int { return $this->internalScore; }
    public function setInternalScore(?int $score): static { $this->internalScore = $score === null ? null : max(1, min(10, $score)); return $this; }
    public function getInternalNote(): ?string { return $this->internalNote; }
    public function setInternalNote(?string $note): static { $this->internalNote = $note !== null ? trim($note) : null; return $this; }
    public function getInternallyRatedAt(): ?\DateTimeImmutable { return $this->internallyRatedAt; }
    public function setInternallyRatedAt(?\DateTimeImmutable $at): static { $this->internallyRatedAt = $at; return $this; }
    
}

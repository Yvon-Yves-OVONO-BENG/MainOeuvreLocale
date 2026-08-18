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
    
}

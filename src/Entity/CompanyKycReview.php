<?php

namespace App\Entity;

use App\Repository\CompanyKycReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyKycReviewRepository::class)]
#[ORM\Table(name: 'company_kyc_review')]
class CompanyKycReview
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';
    public const DECISION_IN_REVIEW = 'in_review';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CompanyKycCase::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CompanyKycCase $kycCase = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reviewer = null;

    #[ORM\Column(length: 20)]
    private ?string $decision = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getKycCase(): ?CompanyKycCase { return $this->kycCase; }
    public function setKycCase(?CompanyKycCase $kycCase): static { $this->kycCase = $kycCase; return $this; }

    public function getReviewer(): ?User { return $this->reviewer; }
    public function setReviewer(?User $reviewer): static { $this->reviewer = $reviewer; return $this; }

    public function getDecision(): ?string { return $this->decision; }
    public function setDecision(string $decision): static { $this->decision = $decision; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
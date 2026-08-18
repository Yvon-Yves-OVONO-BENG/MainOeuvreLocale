<?php

namespace App\Entity;

use App\Repository\CompanyKycCaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyKycCaseRepository::class)]
#[ORM\Table(name: 'company_kyc_case')]
#[ORM\Index(name: 'idx_company_kyc_case_status', columns: ['status'])]
#[ORM\Index(name: 'idx_company_kyc_case_risk_level', columns: ['risk_level'])]
#[ORM\Index(name: 'idx_company_kyc_case_created_at', columns: ['created_at'])]
class CompanyKycCase
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Le compte entreprise = User avec ROLE_COMPANY
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 20)]
    private string $riskLevel = self::RISK_MEDIUM;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\OneToMany(mappedBy: 'kycCase', targetEntity: CompanyKycDocument::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'kycCase', targetEntity: CompanyKycReview::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $reviews;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
        $this->riskLevel = self::RISK_MEDIUM;
    }

    public function getId(): ?int { return $this->id; }

    public function getCompany(): ?User { return $this->company; }
    public function setCompany(?User $company): static { $this->company = $company; return $this; }

    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(?string $companyName): static { $this->companyName = $companyName; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): static { $this->city = $city; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getRiskLevel(): string { return $this->riskLevel; }
    public function setRiskLevel(string $riskLevel): static { $this->riskLevel = $riskLevel; return $this; }

    public function getAdminNote(): ?string { return $this->adminNote; }
    public function setAdminNote(?string $adminNote): static { $this->adminNote = $adminNote; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static { $this->submittedAt = $submittedAt; return $this; }

    public function getReviewedAt(): ?\DateTimeImmutable { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static { $this->reviewedAt = $reviewedAt; return $this; }

    public function getReviewedBy(): ?User { return $this->reviewedBy; }
    public function setReviewedBy(?User $reviewedBy): static { $this->reviewedBy = $reviewedBy; return $this; }

    /** @return Collection<int, CompanyKycDocument> */
    public function getDocuments(): Collection { return $this->documents; }

    public function addDocument(CompanyKycDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setKycCase($this);
        }
        return $this;
    }

    public function removeDocument(CompanyKycDocument $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getKycCase() === $this) {
                $document->setKycCase(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, CompanyKycReview> */
    public function getReviews(): Collection { return $this->reviews; }

    public function addReview(CompanyKycReview $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setKycCase($this);
        }
        return $this;
    }

    public function removeReview(CompanyKycReview $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getKycCase() === $this) {
                $review->setKycCase(null);
            }
        }
        return $this;
    }

    public function getDisplayCompanyName(): string
    {
        if ($this->companyName) {
            return $this->companyName;
        }

        if ($this->company?->getPersonalProfile()?->getFullName()) {
            return $this->company->getPersonalProfile()->getFullName();
        }

        return $this->company?->getEmail() ?? '—';
    }

    public function getDisplayCity(): string
    {
        if ($this->city) {
            return $this->city;
        }

        if ($this->company?->getPersonalProfile()?->getCity()) {
            return $this->company->getPersonalProfile()->getCity();
        }

        return '—';
    }
}
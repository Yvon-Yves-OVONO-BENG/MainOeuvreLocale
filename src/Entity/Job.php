<?php

namespace App\Entity;

use App\Entity\Application;
use App\Entity\Profession;
use App\Entity\Review;
use App\Entity\StatusJob;
use App\Entity\TypeJob;
use App\Entity\User;
use App\Repository\JobRepository;
use App\Util\HashedSlugGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Table(name: 'job')]
#[ORM\UniqueConstraint(name: 'uniq_job_reference', columns: ['reference'])]
#[ORM\UniqueConstraint(name: 'uniq_job_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Job
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    private ?StatusJob $status = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'job')]
    private Collection $reviews;

    #[ORM\OneToMany(targetEntity: Application::class, mappedBy: 'job')]
    private Collection $applications;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    private ?TypeJob $typeJob = null;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    private ?Profession $profession = null;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?int $salaireMin = null;

    #[ORM\Column]
    private ?int $salaireMax = null;

    #[ORM\Column(length: 255)]
    private ?string $experience = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateExpirationAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $mission = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $profil = null;

    #[ORM\Column(length: 64)]
    private ?string $slug = null;

    /**
     * @var Collection<int, FavoriJob>
     */
    #[ORM\OneToMany(mappedBy: 'job', targetEntity: FavoriJob::class, orphanRemoval: true)]
    private Collection $favoris;

    /**
     * @var Collection<int, JobView>
     */
    #[ORM\OneToMany(mappedBy: 'job', targetEntity: JobView::class, orphanRemoval: true)]
    private Collection $views;

    /**
     * @var Collection<int, ReportJob>
     */
    #[ORM\OneToMany(mappedBy: 'job', targetEntity: ReportJob::class, orphanRemoval: true)]
    private Collection $reports;

    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verifiedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminModificationNote = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $modifiedByAdminAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $modifiedByAdmin = null;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private ?string $moderationStatus = 'pending'; // pending, approved, rejected, modified

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationComment = null;

    public function __construct()
    {
        $this->slug = HashedSlugGenerator::generate();
        $this->reviews = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->applications = new ArrayCollection();
        $this->favoris = new ArrayCollection();
        $this->views = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getStatus(): ?StatusJob
    {
        return $this->status;
    }

    public function setStatus(?StatusJob $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    /**
     * @return Collection<int, Application>
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(Application $application): static
    {
        if (!$this->applications->contains($application)) {
            $this->applications->add($application);
            $application->setJob($this);
        }

        return $this;
    }

    public function removeApplication(Application $application): static
    {
        if ($this->applications->removeElement($application)) {
            // set the owning side to null (unless already changed)
            if ($application->getJob() === $this) {
                $application->setJob(null);
            }
        }

        return $this;
    }

    public function getTypeJob(): ?TypeJob
    {
        return $this->typeJob;
    }

    public function setTypeJob(?TypeJob $typeJob): static
    {
        $this->typeJob = $typeJob;

        return $this;
    }

    public function getProfession(): ?Profession
    {
        return $this->profession;
    }

    public function setProfession(?Profession $profession): static
    {
        $this->profession = $profession;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getSalaireMin(): ?int
    {
        return $this->salaireMin;
    }

    public function setSalaireMin(int $salaireMin): static
    {
        $this->salaireMin = $salaireMin;

        return $this;
    }

    public function getSalaireMax(): ?int
    {
        return $this->salaireMax;
    }

    public function setSalaireMax(int $salaireMax): static
    {
        $this->salaireMax = $salaireMax;

        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(string $experience): static
    {
        $this->experience = $experience;

        return $this;
    }

    public function getDateExpirationAt(): ?\DateTimeInterface
    {
        return $this->dateExpirationAt;
    }

    public function setDateExpirationAt(\DateTimeInterface $dateExpirationAt): static
    {
        $this->dateExpirationAt = $dateExpirationAt;

        return $this;
    }

    public function getMission(): ?string
    {
        return $this->mission;
    }

    public function setMission(string $mission): static
    {
        $this->mission = $mission;

        return $this;
    }

    public function getProfil(): ?string
    {
        return $this->profil;
    }

    public function setProfil(string $profil): static
    {
        $this->profil = $profil;

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

    #[ORM\PrePersist]
    public function ensureSlug(): void
    {
        if ($this->slug === null || trim($this->slug) === '') {
            $this->slug = HashedSlugGenerator::generate();
        }
    }


    /**
     * @return Collection<int, FavoriJob>
     */
    public function getFavoris(): Collection
    {
        return $this->favoris;
    }

    public function addFavori(FavoriJob $favori): self
    {
        if (!$this->favoris->contains($favori)) {
            $this->favoris->add($favori);
            $favori->setJob($this);
        }
        return $this;
    }

    public function removeFavori(FavoriJob $favori): self
    {
        if ($this->favoris->removeElement($favori)) {
            if ($favori->getJob() === $this) {
                $favori->setJob(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, JobView>
     */
    public function getViews(): Collection
    {
        return $this->views;
    }

    public function addView(JobView $view): self
    {
        if (!$this->views->contains($view)) {
            $this->views->add($view);
            $view->setJob($this);
        }
        return $this;
    }

    public function removeView(JobView $view): self
    {
        if ($this->views->removeElement($view)) {
            if ($view->getJob() === $this) {
                $view->setJob(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ReportJob>
     */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(ReportJob $report): self
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setJob($this);
        }

        return $this;
    }

    public function removeReport(ReportJob $report): self
    {
        // Comme job est NOT NULL dans ReportJob, on ne fait pas setJob(null)
        $this->reports->removeElement($report);

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


     /**
     * Getter/Setter pour verifiedBy
     */
    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?User $verifiedBy): static
    {
        $this->verifiedBy = $verifiedBy;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getAdminModificationNote(): ?string
    {
        return $this->adminModificationNote;
    }

    public function setAdminModificationNote(?string $adminModificationNote): static
    {
        $this->adminModificationNote = $adminModificationNote;
        return $this;
    }

    public function getModifiedByAdminAt(): ?\DateTimeInterface
    {
        return $this->modifiedByAdminAt;
    }

    public function setModifiedByAdminAt(?\DateTimeInterface $modifiedByAdminAt): static
    {
        $this->modifiedByAdminAt = $modifiedByAdminAt;
        return $this;
    }

    public function getModifiedByAdmin(): ?User
    {
        return $this->modifiedByAdmin;
    }

    public function setModifiedByAdmin(?User $modifiedByAdmin): static
    {
        $this->modifiedByAdmin = $modifiedByAdmin;
        return $this;
    }

    public function getModerationStatus(): ?string
    {
        return $this->moderationStatus;
    }

    public function setModerationStatus(string $moderationStatus): static
    {
        $this->moderationStatus = $moderationStatus;
        return $this;
    }

    public function getModerationComment(): ?string
    {
        return $this->moderationComment;
    }

    public function setModerationComment(?string $moderationComment): static
    {
        $this->moderationComment = $moderationComment;
        return $this;
    }

    // Méthodes helper
    public function isApproved(): bool
    {
        return $this->moderationStatus === 'approved';
    }

    public function isPending(): bool
    {
        return $this->moderationStatus === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->moderationStatus === 'rejected';
    }

    public function approve(User $admin, ?string $comment = null): void
    {
        $this->moderationStatus = 'approved';
        $this->verifiedBy = $admin;
        $this->verifiedAt = new \DateTime();
        $this->moderationComment = $comment;
    }

    public function reject(User $admin, string $reason): void
    {
        $this->moderationStatus = 'rejected';
        $this->verifiedBy = $admin;
        $this->verifiedAt = new \DateTime();
        $this->moderationComment = $reason;
    }

    public function markAsModified(User $admin, string $note): void
    {
        $this->moderationStatus = 'modified';
        $this->modifiedByAdmin = $admin;
        $this->modifiedByAdminAt = new \DateTime();
        $this->adminModificationNote = $note;
    }

}

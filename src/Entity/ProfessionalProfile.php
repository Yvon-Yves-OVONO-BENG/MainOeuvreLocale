<?php

namespace App\Entity;

use App\Repository\ProfessionalProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfessionalProfileRepository::class)]
class ProfessionalProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'professionalProfile')]
    private ?Profession $profession = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $bio = null;

    #[ORM\Column(length: 255)]
    private ?string $experienceYears = null;

    #[ORM\ManyToOne(inversedBy: 'professionalProfile')]
    private ?StatusProfile $statusProfile = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 13, scale: 10, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $geolocationEnabled = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $locationUpdatedAt = null;

    /** @var Collection<int, ProfessionalMedia> */
    #[ORM\OneToMany(targetEntity: ProfessionalMedia::class, mappedBy: 'professionalProfile', orphanRemoval: false)]
    private Collection $professionalMedia;

    /** @var Collection<int, Conversation> */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'professionalProfile', orphanRemoval: false)]
    private Collection $conversations;

    #[ORM\OneToOne(inversedBy: 'professionalProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, Favorite> */
    #[ORM\OneToMany(targetEntity: Favorite::class, mappedBy: 'professionalProfile', orphanRemoval: false)]
    private Collection $favorites;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /** @var Collection<int, Profession> */
    #[ORM\ManyToMany(targetEntity: Profession::class, inversedBy: 'profilesSkills')]
    #[ORM\JoinTable(name: 'professional_profile_profession_skill')]
    private Collection $skills;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $experience = null;

    #[ORM\Column(length: 255)]
    private ?string $cv = null;

    /** @var Collection<int, View> */
    #[ORM\OneToMany(targetEntity: View::class, mappedBy: 'professionalProfile', orphanRemoval: false)]
    private Collection $views;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ✅ Bool en DB + Date/By : on les garde MAIS on les synchronise
    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verifiedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $verifiedBy = null;

    #[ORM\OneToMany(mappedBy: 'professionalProfile', targetEntity: TalentAvailability::class, orphanRemoval: true)]
    private Collection $availabilityDates;

    public function __construct()
    {
        $this->professionalMedia = new ArrayCollection();
        $this->conversations     = new ArrayCollection();
        $this->favorites         = new ArrayCollection();
        $this->skills            = new ArrayCollection();
        $this->views             = new ArrayCollection();
        $this->availabilityDates = new ArrayCollection();
    }

    // ==========================
    // Getters / Setters basiques
    // ==========================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfession(): ?Profession
    {
        return $this->profession;
    }

    public function setProfession(?Profession $profession): self
    {
        $this->profession = $profession;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getExperienceYears(): ?string
    {
        return $this->experienceYears;
    }

    public function setExperienceYears(?string $experienceYears): self
    {
        $this->experienceYears = $experienceYears;
        return $this;
    }

    public function getStatusProfile(): ?StatusProfile
    {
        return $this->statusProfile;
    }

    public function setStatusProfile(?StatusProfile $statusProfile): self
    {
        $this->statusProfile = $statusProfile;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function isGeolocationEnabled(): bool
    {
        return $this->geolocationEnabled;
    }

    public function getGeolocationEnabled(): bool
    {
        return $this->geolocationEnabled;
    }

    public function setGeolocationEnabled(bool $geolocationEnabled): self
    {
        $this->geolocationEnabled = $geolocationEnabled;
        return $this;
    }

    public function getLocationUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->locationUpdatedAt;
    }

    public function setLocationUpdatedAt(?\DateTimeImmutable $locationUpdatedAt): self
    {
        $this->locationUpdatedAt = $locationUpdatedAt;
        return $this;
    }

    public function setCoordinates(float $latitude, float $longitude): self
    {
        $this->latitude = number_format($latitude, 8, '.', '');
        $this->longitude = number_format($longitude, 10, '.', '');
        $this->locationUpdatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function disableGeolocation(): self
    {
        $this->geolocationEnabled = false;
        $this->latitude = null;
        $this->longitude = null;
        $this->locationUpdatedAt = null;
        return $this;
    }

    public function hasUsableLocation(): bool
    {
        return $this->geolocationEnabled
            && $this->latitude !== null
            && $this->longitude !== null;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): self
    {
        $this->experience = $experience;
        return $this;
    }

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): self
    {
        $this->cv = $cv;
        return $this;
    }

    // ==========================
    // Collections
    // ==========================

    /** @return Collection<int, ProfessionalMedia> */
    public function getProfessionalMedia(): Collection
    {
        return $this->professionalMedia;
    }

    // ✅ Nom correct
    public function addProfessionalMedia(ProfessionalMedia $media): self
    {
        if (!$this->professionalMedia->contains($media)) {
            $this->professionalMedia->add($media);
            $media->setProfessionalProfile($this);
        }
        return $this;
    }

    public function removeProfessionalMedia(ProfessionalMedia $media): self
    {
        if ($this->professionalMedia->removeElement($media)) {
            if ($media->getProfessionalProfile() === $this) {
                $media->setProfessionalProfile(null);
            }
        }
        return $this;
    }

    // ✅ Compat: garde tes anciens noms (typo) pour ne pas casser ton code
    public function addProfessionnalMedia(ProfessionalMedia $professionalMedia): self
    {
        return $this->addProfessionalMedia($professionalMedia);
    }

    public function removeProfessionnalMedia(ProfessionalMedia $professionalMedia): self
    {
        return $this->removeProfessionalMedia($professionalMedia);
    }

    /** @return Collection<int, Conversation> */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): self
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setProfessionalProfile($this);
        }
        return $this;
    }

    public function removeConversation(Conversation $conversation): self
    {
        if ($this->conversations->removeElement($conversation)) {
            if ($conversation->getProfessionalProfile() === $this) {
                $conversation->setProfessionalProfile(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Favorite> */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }

    /** @return Collection<int, Profession> */
    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Profession $profession): self
    {
        if (!$this->skills->contains($profession)) {
            $this->skills->add($profession);
        }
        return $this;
    }

    public function removeSkill(Profession $profession): self
    {
        $this->skills->removeElement($profession);
        return $this;
    }

    /** @return Collection<int, View> */
    public function getViews(): Collection
    {
        return $this->views;
    }

    public function addView(View $view): self
    {
        if (!$this->views->contains($view)) {
            $this->views->add($view);
            $view->setProfessionalProfile($this);
        }
        return $this;
    }

    public function removeView(View $view): self
    {
        if ($this->views->removeElement($view)) {
            if ($view->getProfessionalProfile() === $this) {
                $view->setProfessionalProfile(null);
            }
        }
        return $this;
    }

    // ==========================
    // Vérification (sync propre)
    // ==========================

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    // Optionnel (parfois utile en forms/services)
    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        // ✅ sync avec la date
        if ($isVerified) {
            if ($this->verifiedAt === null) {
                $this->verifiedAt = new \DateTime();
            }
        } else {
            $this->verifiedAt = null;
            $this->verifiedBy = null;
        }

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;

        // ✅ sync avec le bool
        $this->isVerified = ($verifiedAt !== null);

        if ($verifiedAt === null) {
            $this->verifiedBy = null;
        }

        return $this;
    }

    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?User $verifiedBy): self
    {
        $this->verifiedBy = $verifiedBy;
        return $this;
    }

    // ✅ Helpers métier (propre)
    public function verify(?User $by = null, ?\DateTimeInterface $at = null): self
    {
        $this->isVerified = true;
        $this->verifiedAt = $at ?? new \DateTime();
        $this->verifiedBy = $by;

        return $this;
    }

    public function unverify(): self
    {
        $this->isVerified = false;
        $this->verifiedAt = null;
        $this->verifiedBy = null;

        return $this;
    }

    // ✅ Compat (si jamais tu l’avais déjà utilisé quelque part)
    public function hasVerifiedAt(): bool
    {
        return $this->verifiedAt !== null;
    }

     /**
     * @return Collection<int, TalentAvailability>
     */
    public function getAvailabilityDates(): Collection
    {
        return $this->availabilityDates;
    }

    public function addAvailabilityDate(TalentAvailability $availabilityDate): static
    {
        if (!$this->availabilityDates->contains($availabilityDate)) {
            $this->availabilityDates->add($availabilityDate);
            $availabilityDate->setProfessionalProfile($this);
        }

        return $this;
    }

    public function removeAvailabilityDate(TalentAvailability $availabilityDate): static
    {
        if ($this->availabilityDates->removeElement($availabilityDate)) {
            if ($availabilityDate->getProfessionalProfile() === $this) {
                $availabilityDate->setProfessionalProfile(null);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\ProfessionRepository;
use App\Util\HashedSlugGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfessionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_profession_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Profession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $profession = null;

    #[ORM\Column(length: 64)]
    private ?string $slug = null;

    /**
     * @var Collection<int, ProfessionalProfile>
     */
    #[ORM\OneToMany(targetEntity: ProfessionalProfile::class, mappedBy: 'profession')]
    private Collection $professionalProfile;

    #[ORM\ManyToOne(inversedBy: 'professions')]
    private ?Categorie $categorie = null;

    #[ORM\OneToMany(targetEntity: Job::class, mappedBy: 'profession')]
    private Collection $jobs;

    #[ORM\ManyToMany(targetEntity: ProfessionalProfile::class, mappedBy: 'skills')]
    private Collection $profilesSkills;

    #[ORM\Column(length: 255)]
    private ?string $photo = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    public function __construct()
    {
        $this->slug = HashedSlugGenerator::generate();
        $this->professionalProfile = new ArrayCollection();
        $this->jobs = new ArrayCollection();
        $this->profilesSkills = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function setProfession(string $profession): static
    {
        $this->profession = $profession;

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
     * @return Collection<int, ProfessionalProfile>
     */
    public function getProfessionalProfile(): Collection
    {
        return $this->professionalProfile;
    }

    public function addProfessionnalProfile(ProfessionalProfile $professionalProfile): static
    {
        if (!$this->professionalProfile->contains($professionalProfile)) {
            $this->professionalProfile->add($professionalProfile);
            $professionalProfile->setProfession($this);
        }

        return $this;
    }

    public function removeProfessionnalProfile(ProfessionalProfile $professionalProfile): static
    {
        if ($this->professionalProfile->removeElement($professionalProfile)) {
            // set the owning side to null (unless already changed)
            if ($professionalProfile->getProfession() === $this) {
                $professionalProfile->setProfession(null);
            }
        }

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    /**
     * @return Collection<int, Job>
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function addJob(Job $job): static
    {
        if (!$this->jobs->contains($job)) {
            $this->jobs->add($job);
            $job->setProfession($this);
        }

        return $this;
    }

    public function removeJob(Job $job): static
    {
        if ($this->jobs->removeElement($job)) {
            // set the owning side to null (unless already changed)
            if ($job->getProfession() === $this) {
                $job->setProfession(null);
            }
        }

        return $this;
    }

    public function getProfilesSkills(): Collection
    {
        return $this->profilesSkills;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): static
    {
        $this->photo = $photo;

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

}

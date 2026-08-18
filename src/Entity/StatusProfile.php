<?php

namespace App\Entity;

use App\Repository\StatusProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusProfileRepository::class)]
class StatusProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    /**
     * @var Collection<int, ProfessionalProfile>
     */
    #[ORM\OneToMany(targetEntity: ProfessionalProfile::class, mappedBy: 'status')]
    private Collection $professionalProfile;

    public function __construct()
    {
        $this->professionalProfile = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    /**
     * @return Collection<int, ProfessionalProfile>
     */
    public function getProfessionalProfile(): Collection
    {
        return $this->professionalProfile;
    }

    public function addProfessionalProfile(ProfessionalProfile $professionalProfile): static
    {
        if (!$this->professionalProfile->contains($professionalProfile)) {
            $this->professionalProfile->add($professionalProfile);
            $professionalProfile->setStatusProfile($this);
        }

        return $this;
    }

    public function removeProfessionalProfile(ProfessionalProfile $professionalProfile): static
    {
        if ($this->professionalProfile->removeElement($professionalProfile)) {
            // set the owning side to null (unless already changed)
            if ($professionalProfile->getStatusProfile() === $this) {
                $professionalProfile->setStatusProfile(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->status;
    }

}

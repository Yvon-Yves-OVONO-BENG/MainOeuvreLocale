<?php

namespace App\Entity;

use App\Repository\SexeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SexeRepository::class)]
class Sexe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sexe = null;

    /**
     * @var Collection<int, PersonalProfile>
     */
    #[ORM\OneToMany(targetEntity: PersonalProfile::class, mappedBy: 'sexe')]
    private Collection $personalProfiles;

    public function __construct()
    {
        $this->personalProfiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    /**
     * @return Collection<int, PersonalProfile>
     */
    public function getPersonalProfiles(): Collection
    {
        return $this->personalProfiles;
    }

    public function addPersonalProfile(PersonalProfile $personalProfile): static
    {
        if (!$this->personalProfiles->contains($personalProfile)) {
            $this->personalProfiles->add($personalProfile);
            $personalProfile->setSexe($this);
        }

        return $this;
    }

    public function removePersonalProfile(PersonalProfile $personalProfile): static
    {
        if ($this->personalProfiles->removeElement($personalProfile)) {
            // set the owning side to null (unless already changed)
            if ($personalProfile->getSexe() === $this) {
                $personalProfile->setSexe(null);
            }
        }

        return $this;
    }
}

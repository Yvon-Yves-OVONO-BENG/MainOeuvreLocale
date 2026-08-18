<?php

namespace App\Entity;

use App\Repository\TalentAvailabilityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TalentAvailabilityRepository::class)]
#[ORM\Table(name: 'talent_availability')]
#[ORM\UniqueConstraint(name: 'uniq_profile_available_date', columns: ['professional_profile_id', 'available_date'])]
class TalentAvailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProfessionalProfile::class, inversedBy: 'availabilityDates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProfessionalProfile $professionalProfile = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $availableDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfessionalProfile(): ?ProfessionalProfile
    {
        return $this->professionalProfile;
    }

    public function setProfessionalProfile(?ProfessionalProfile $professionalProfile): static
    {
        $this->professionalProfile = $professionalProfile;
        return $this;
    }

    public function getAvailableDate(): ?\DateTimeInterface
    {
        return $this->availableDate;
    }

    public function setAvailableDate(\DateTimeInterface $availableDate): static
    {
        $this->availableDate = $availableDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
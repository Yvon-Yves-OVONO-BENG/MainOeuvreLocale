<?php

namespace App\Entity;

use App\Repository\ViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewRepository::class)]
#[ORM\Table(name: 'profile_view')]
#[ORM\Index(columns: ['professional_profile_id', 'created_at'], name: 'idx_view_profile_created')]
#[ORM\Index(columns: ['viewer_id', 'created_at'], name: 'idx_view_viewer_created')]
#[ORM\UniqueConstraint(name: 'uniq_view_per_day', columns: ['viewer_id', 'professional_profile_id', 'view_date'])]
class View
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Celui qui visite (user connecté)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $viewer = null;

    // Le talent (profil pro) visité
    #[ORM\ManyToOne(inversedBy: 'views')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProfessionalProfile $professionalProfile = null;

    // Date "jour" pour bloquer les refresh (YYYY-MM-DD)
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $viewDate = null;

    // Date/heure exacte de la vue
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getViewer(): ?User
    {
        return $this->viewer;
    }

    public function setViewer(?User $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
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

    public function getViewDate(): ?\DateTimeInterface
    {
        return $this->viewDate;
    }

    public function setViewDate(\DateTimeInterface $viewDate): static
    {
        $this->viewDate = $viewDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

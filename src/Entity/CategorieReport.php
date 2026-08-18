<?php

namespace App\Entity;

use App\Repository\CategorieReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieReportRepository::class)]
#[ORM\Table(name: 'categorie_report')]
class CategorieReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private string $label = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(mappedBy: 'categorie', targetEntity: Report::class)]
    private Collection $reports;

    public function __construct()
    {
        $this->reports = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    /** @return Collection<int, Report> */
    public function getReports(): Collection { return $this->reports; }

    public function __toString(): string
    {
        return $this->label;
    }
}
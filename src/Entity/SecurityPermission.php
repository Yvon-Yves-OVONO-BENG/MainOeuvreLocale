<?php

namespace App\Entity;

use App\Repository\SecurityPermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecurityPermissionRepository::class)]
#[ORM\Table(name: 'security_permission')]
class SecurityPermission
{
    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 180)]
    private ?string $label = null;

    #[ORM\Column(length: 100)]
    private ?string $domain = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $riskLevel = self::RISK_LOW;

    #[ORM\ManyToMany(targetEntity: RoleDefinition::class, mappedBy: 'permissions')]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function getDomain(): ?string { return $this->domain; }
    public function setDomain(string $domain): static { $this->domain = $domain; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getRiskLevel(): string { return $this->riskLevel; }
    public function setRiskLevel(string $riskLevel): static { $this->riskLevel = $riskLevel; return $this; }

    /**
     * @return Collection<int, RoleDefinition>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }
}
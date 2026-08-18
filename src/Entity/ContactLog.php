<?php

namespace App\Entity;

use App\Repository\ContactLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactLogRepository::class)]
#[ORM\Table(name: 'contact_log')]
class ContactLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $targetUser = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null; // 'email' ou 'phone'

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getTargetUser(): ?User { return $this->targetUser; }
    public function setTargetUser(?User $targetUser): self { $this->targetUser = $targetUser; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
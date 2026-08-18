<?php

namespace App\Entity;

use App\Repository\ModerationRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModerationRuleRepository::class)]
#[ORM\Table(name: 'moderation_rule')]
class ModerationRule
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 60)]
    private string $category = 'general'; // fraud|spam|identity|jobs|messages|general

    #[ORM\Column(length: 20)]
    private string $severity = 'warn'; // info|warn|critical

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $recommendedAction = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters/setters (génère via IDE si tu veux)
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $c): self { $this->category = $c; return $this; }
    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $s): self { $this->severity = $s; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $d): self { $this->description = $d; return $this; }
    public function getRecommendedAction(): ?string { return $this->recommendedAction; }
    public function setRecommendedAction(?string $a): self { $this->recommendedAction = $a; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $b): self { $this->isActive = $b; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $d): self { $this->updatedAt = $d; return $this; }
}
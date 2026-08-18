<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_blocked_user')]
#[ORM\UniqueConstraint(name: 'uniq_chat_blocked_user_pair', columns: ['blocker_identifier', 'blocked_identifier'])]
class ChatBlockedUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'blocker_identifier', length: 180)]
    private string $blockerIdentifier;

    #[ORM\Column(name: 'blocked_identifier', length: 180)]
    private string $blockedIdentifier;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $blockerIdentifier = '', string $blockedIdentifier = '')
    {
        $this->blockerIdentifier = $blockerIdentifier;
        $this->blockedIdentifier = $blockedIdentifier;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getBlockerIdentifier(): string { return $this->blockerIdentifier; }

    public function setBlockerIdentifier(string $blockerIdentifier): self
    {
        $this->blockerIdentifier = $blockerIdentifier;
        $this->touch();
        return $this;
    }

    public function getBlockedIdentifier(): string { return $this->blockedIdentifier; }

    public function setBlockedIdentifier(string $blockedIdentifier): self
    {
        $this->blockedIdentifier = $blockedIdentifier;
        $this->touch();
        return $this;
    }

    public function getLabel(): ?string { return $this->label; }

    public function setLabel(?string $label): self
    {
        $this->label = $label ?: null;
        $this->touch();
        return $this;
    }

    public function getReason(): ?string { return $this->reason; }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason ?: null;
        $this->touch();
        return $this;
    }

    public function isActive(): bool { return $this->active; }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
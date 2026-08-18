<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_group')]
class ChatGroup
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_RECOMMENDED = 'recommended';
    public const VISIBILITY_PUBLIC = 'public';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 140)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private string $visibility = self::VISIBILITY_PRIVATE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(nullable: true)]
    private ?int $memberLimit = 100;

    #[ORM\Column]
    private bool $approvalRequired = true;

    #[ORM\Column]
    private bool $allowFiles = true;

    #[ORM\Column]
    private bool $allowVoice = true;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }

    public function setName(string $name): self
    {
        $this->name = trim($name);
        $this->touch();
        return $this;
    }

    public function getSlug(): string { return $this->slug; }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        $this->touch();
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }

    public function setDescription(?string $description): self
    {
        $this->description = $description ? trim($description) : null;
        $this->touch();
        return $this;
    }

    public function getVisibility(): string { return $this->visibility; }

    public function setVisibility(string $visibility): self
    {
        $allowed = [
            self::VISIBILITY_PRIVATE,
            self::VISIBILITY_RECOMMENDED,
            self::VISIBILITY_PUBLIC,
        ];

        $this->visibility = in_array($visibility, $allowed, true) ? $visibility : self::VISIBILITY_PRIVATE;
        $this->touch();

        return $this;
    }

    public function getOwner(): ?User { return $this->owner; }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        $this->touch();
        return $this;
    }

    public function getMemberLimit(): ?int { return $this->memberLimit; }

    public function setMemberLimit(?int $memberLimit): self
    {
        $this->memberLimit = $memberLimit;
        $this->touch();
        return $this;
    }

    public function isApprovalRequired(): bool { return $this->approvalRequired; }

    public function setApprovalRequired(bool $approvalRequired): self
    {
        $this->approvalRequired = $approvalRequired;
        $this->touch();
        return $this;
    }

    public function isAllowFiles(): bool { return $this->allowFiles; }

    public function setAllowFiles(bool $allowFiles): self
    {
        $this->allowFiles = $allowFiles;
        $this->touch();
        return $this;
    }

    public function isAllowVoice(): bool { return $this->allowVoice; }

    public function setAllowVoice(bool $allowVoice): self
    {
        $this->allowVoice = $allowVoice;
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
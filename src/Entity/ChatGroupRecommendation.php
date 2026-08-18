<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_group_recommendation')]
class ChatGroupRecommendation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_IGNORED = 'ignored';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatGroup::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChatGroup $chatGroup = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recommended_by_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $recommendedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recommended_to_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $recommendedTo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getChatGroup(): ?ChatGroup { return $this->chatGroup; }

    public function setChatGroup(?ChatGroup $chatGroup): self
    {
        $this->chatGroup = $chatGroup;
        return $this;
    }

    public function getRecommendedBy(): ?User { return $this->recommendedBy; }

    public function setRecommendedBy(?User $recommendedBy): self
    {
        $this->recommendedBy = $recommendedBy;
        return $this;
    }

    public function getRecommendedTo(): ?User { return $this->recommendedTo; }

    public function setRecommendedTo(?User $recommendedTo): self
    {
        $this->recommendedTo = $recommendedTo;
        return $this;
    }

    public function getNote(): ?string { return $this->note; }

    public function setNote(?string $note): self
    {
        $this->note = $note ?: null;
        return $this;
    }

    public function getStatus(): string { return $this->status; }

    public function setStatus(string $status): self
    {
        $this->status = in_array($status, [self::STATUS_PENDING, self::STATUS_REQUESTED, self::STATUS_IGNORED], true)
            ? $status
            : self::STATUS_PENDING;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
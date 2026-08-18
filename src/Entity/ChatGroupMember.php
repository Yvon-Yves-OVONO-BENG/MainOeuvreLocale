<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_group_member')]
#[ORM\UniqueConstraint(name: 'uniq_chat_group_member_user', columns: ['chat_group_id', 'user_id'])]
class ChatGroupMember
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MEMBER = 'member';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REMOVED = 'removed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatGroup::class)]
    #[ORM\JoinColumn(name: 'chat_group_id', nullable: false, onDelete: 'CASCADE')]
    private ?ChatGroup $chatGroup = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_MEMBER;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private bool $muted = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getChatGroup(): ?ChatGroup { return $this->chatGroup; }

    public function setChatGroup(?ChatGroup $chatGroup): self
    {
        $this->chatGroup = $chatGroup;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getRole(): string { return $this->role; }

    public function setRole(string $role): self
    {
        $this->role = in_array($role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MEMBER], true)
            ? $role
            : self::ROLE_MEMBER;

        return $this;
    }

    public function getStatus(): string { return $this->status; }

    public function setStatus(string $status): self
    {
        $this->status = in_array($status, [self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_REMOVED], true)
            ? $status
            : self::STATUS_ACTIVE;

        return $this;
    }

    public function isMuted(): bool { return $this->muted; }

    public function setMuted(bool $muted): self
    {
        $this->muted = $muted;
        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }
}
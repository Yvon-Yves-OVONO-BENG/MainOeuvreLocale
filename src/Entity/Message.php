<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\MessageReaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
#[ORM\Index(name: 'idx_message_created_at', columns: ['created_at'])]
class Message
{
    public const TYPE_TEXT   = 'text';
    public const TYPE_FILE   = 'file';
    public const TYPE_CALL   = 'call';
    public const TYPE_SYSTEM = 'system';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $replyTo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentUrl = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $muted = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $archived = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $markedUnread = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $deleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reportedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reportReason = null;

    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageReaction::class, orphanRemoval: true)]
    private Collection $reactions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reactions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(?Conversation $c): self { $this->conversation = $c; return $this; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $u): self { $this->sender = $u; return $this; }

    public function getReplyTo(): ?self { return $this->replyTo; }
    public function setReplyTo(?self $m): self { $this->replyTo = $m; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $this->content = $content; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getAttachmentUrl(): ?string { return $this->attachmentUrl; }
    public function setAttachmentUrl(?string $url): self { $this->attachmentUrl = $url; return $this; }

    public function getMeta(): ?array { return $this->meta; }
    public function setMeta(?array $meta): self { $this->meta = $meta; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getEditedAt(): ?\DateTimeImmutable { return $this->editedAt; }
    public function setEditedAt(?\DateTimeImmutable $dt): self { $this->editedAt = $dt; return $this; }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $dt): self { $this->deletedAt = $dt; return $this; }

    public function getDeliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeImmutable $dt): self { $this->deliveredAt = $dt; return $this; }

    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $dt): self { $this->readAt = $dt; return $this; }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    public function setMuted(bool $muted): self
    {
        $this->muted = $muted;
        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;
        return $this;
    }

    public function isMarkedUnread(): bool
    {
        return $this->markedUnread;
    }

    public function setMarkedUnread(bool $markedUnread): self
    {
        $this->markedUnread = $markedUnread;
        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function getReportedAt(): ?\DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(?\DateTimeImmutable $reportedAt): self
    {
        $this->reportedAt = $reportedAt;
        return $this;
    }

    public function getReportReason(): ?string
    {
        return $this->reportReason;
    }

    public function setReportReason(?string $reportReason): self
    {
        $this->reportReason = $reportReason;
        return $this;
    }

    public function isMine(User $me): bool
    {
        return $this->sender?->getId() === $me->getId();
    }

    public function isDelivered(): bool
    {
        return $this->deliveredAt !== null;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function getStatus(): string
    {
        if ($this->readAt !== null) return 'read';
        if ($this->deliveredAt !== null) return 'delivered';
        return 'sent';
    }

    public function markEdited(): self
    {
        $this->editedAt = new \DateTimeImmutable();
        return $this;
    }

    public function softDelete(): self
    {
        $this->deleted = true;
        $this->deletedAt = new \DateTimeImmutable();
        $this->content = null;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deleted || $this->deletedAt !== null;
    }

    public function markAsUnread(): self
    {
        $this->markedUnread = true;
        return $this;
    }

    public function markAsRead(): self
    {
        $this->markedUnread = false;
        $this->readAt = new \DateTimeImmutable();
        return $this;
    }

    public function archive(): self
    {
        $this->archived = true;
        return $this;
    }

    public function unarchive(): self
    {
        $this->archived = false;
        return $this;
    }

    public function mute(): self
    {
        $this->muted = true;
        return $this;
    }

    public function unmute(): self
    {
        $this->muted = false;
        return $this;
    }

    public function report(?string $reason = null): self
    {
        $this->reportedAt = new \DateTimeImmutable();
        $this->reportReason = $reason;
        return $this;
    }

    /**
     * @return Collection<int, MessageReaction>
     */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    public function addReaction(MessageReaction $reaction): static
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions->add($reaction);
            $reaction->setMessage($this);
        }

        return $this;
    }

    public function removeReaction(MessageReaction $reaction): static
    {
        if ($this->reactions->removeElement($reaction)) {
            if ($reaction->getMessage() === $this) {
                $reaction->setMessage(null);
            }
        }

        return $this;
    }
}
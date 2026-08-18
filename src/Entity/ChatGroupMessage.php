<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_group_message')]
class ChatGroupMessage
{
    public const TYPE_TEXT = 'text';
    public const TYPE_FILE = 'file';
    public const TYPE_IMAGE = 'image';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_VOICE = 'voice';
    public const TYPE_PDF = 'pdf';
    public const TYPE_WORD = 'word';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatGroup::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChatGroup $chatGroup = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentUrl = null;

    #[ORM\Column(type: 'json')]
    private array $meta = [];

    #[ORM\Column]
    private bool $deleted = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $deletedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $replyTo = null;

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

    public function getSender(): ?User { return $this->sender; }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getType(): string { return $this->type; }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getContent(): ?string { return $this->content; }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getAttachmentUrl(): ?string { return $this->attachmentUrl; }

    public function setAttachmentUrl(?string $attachmentUrl): self
    {
        $this->attachmentUrl = $attachmentUrl;
        return $this;
    }

    public function getMeta(): array { return $this->meta; }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function isDeleted(): bool { return $this->deleted; }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function getDeletedBy(): ?User { return $this->deletedBy; }

    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getReplyTo(): ?self
    {
        return $this->replyTo;
    }

    public function setReplyTo(?self $replyTo): self
    {
        $this->replyTo = $replyTo;

        return $this;
    }
}
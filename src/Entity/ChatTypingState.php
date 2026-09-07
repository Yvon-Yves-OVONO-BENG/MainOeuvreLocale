<?php

namespace App\Entity;

use App\Repository\ChatTypingStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** État de frappe éphémère persistant quelques secondes pour le polling AJAX. */
#[ORM\Entity(repositoryClass: ChatTypingStateRepository::class)]
#[ORM\Table(name: 'chat_typing_state')]
#[ORM\UniqueConstraint(name: 'uniq_chat_typing_conversation_user', columns: ['conversation_id', 'user_id'])]
class ChatTypingState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private bool $typing = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getConversation(): ?Conversation { return $this->conversation; }
    public function setConversation(Conversation $conversation): self { $this->conversation = $conversation; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function isTyping(): bool { return $this->typing; }
    public function setTyping(bool $typing): self { $this->typing = $typing; $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}

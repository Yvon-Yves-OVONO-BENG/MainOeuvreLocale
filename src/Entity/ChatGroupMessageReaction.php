<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_group_message_reaction')]
#[ORM\UniqueConstraint(name: 'uniq_group_message_reaction_user_emoji', columns: ['message_id', 'user_id', 'emoji'])]
class ChatGroupMessageReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatGroupMessage::class)]
    #[ORM\JoinColumn(name: 'message_id', nullable: false, onDelete: 'CASCADE')]
    private ?ChatGroupMessage $message = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 32)]
    private string $emoji = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getMessage(): ?ChatGroupMessage { return $this->message; }

    public function setMessage(?ChatGroupMessage $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getEmoji(): string { return $this->emoji; }

    public function setEmoji(string $emoji): self
    {
        $this->emoji = mb_substr(trim($emoji), 0, 32);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
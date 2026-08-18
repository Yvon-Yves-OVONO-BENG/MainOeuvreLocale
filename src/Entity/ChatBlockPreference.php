<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_block_preference')]
#[ORM\UniqueConstraint(name: 'uniq_chat_block_preference_user', columns: ['user_identifier'])]
class ChatBlockPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_identifier', length: 180)]
    private string $userIdentifier;

    #[ORM\Column]
    private bool $blockUnknownUsers = false;

    #[ORM\Column]
    private bool $blockCalls = false;

    #[ORM\Column]
    private bool $blockFiles = false;

    #[ORM\Column]
    private bool $blockLinks = false;

    #[ORM\Column]
    private bool $profanityFilter = true;

    #[ORM\Column]
    private bool $hideBlockedConversations = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $userIdentifier = '')
    {
        $this->userIdentifier = $userIdentifier;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserIdentifier(): string { return $this->userIdentifier; }

    public function setUserIdentifier(string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;
        $this->touch();
        return $this;
    }

    public function isBlockUnknownUsers(): bool { return $this->blockUnknownUsers; }

    public function setBlockUnknownUsers(bool $blockUnknownUsers): self
    {
        $this->blockUnknownUsers = $blockUnknownUsers;
        $this->touch();
        return $this;
    }

    public function isBlockCalls(): bool { return $this->blockCalls; }

    public function setBlockCalls(bool $blockCalls): self
    {
        $this->blockCalls = $blockCalls;
        $this->touch();
        return $this;
    }

    public function isBlockFiles(): bool { return $this->blockFiles; }

    public function setBlockFiles(bool $blockFiles): self
    {
        $this->blockFiles = $blockFiles;
        $this->touch();
        return $this;
    }

    public function isBlockLinks(): bool { return $this->blockLinks; }

    public function setBlockLinks(bool $blockLinks): self
    {
        $this->blockLinks = $blockLinks;
        $this->touch();
        return $this;
    }

    public function isProfanityFilter(): bool { return $this->profanityFilter; }

    public function setProfanityFilter(bool $profanityFilter): self
    {
        $this->profanityFilter = $profanityFilter;
        $this->touch();
        return $this;
    }

    public function isHideBlockedConversations(): bool { return $this->hideBlockedConversations; }

    public function setHideBlockedConversations(bool $hideBlockedConversations): self
    {
        $this->hideBlockedConversations = $hideBlockedConversations;
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
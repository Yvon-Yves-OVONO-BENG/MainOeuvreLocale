<?php

namespace App\Entity;

use App\Repository\UserTwoFactorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserTwoFactorRepository::class)]
#[ORM\Table(name: 'user_two_factor')]
class UserTwoFactor
{
    public const METHOD_TOTP = 'totp';
    public const METHOD_EMAIL = 'email';
    public const METHOD_SMS = 'sms';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $enabled = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastChallengeAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): static { $this->enabled = $enabled; return $this; }

    public function getMethod(): ?string { return $this->method; }
    public function setMethod(?string $method): static { $this->method = $method; return $this; }

    public function getSecret(): ?string { return $this->secret; }
    public function setSecret(?string $secret): static { $this->secret = $secret; return $this; }

    public function getBackupCodes(): ?array { return $this->backupCodes; }
    public function setBackupCodes(?array $backupCodes): static { $this->backupCodes = $backupCodes; return $this; }

    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static { $this->confirmedAt = $confirmedAt; return $this; }

    public function getLastChallengeAt(): ?\DateTimeImmutable { return $this->lastChallengeAt; }
    public function setLastChallengeAt(?\DateTimeImmutable $lastChallengeAt): static { $this->lastChallengeAt = $lastChallengeAt; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getUiStatus(): string
    {
        if ($this->enabled && $this->confirmedAt !== null) {
            return 'enabled';
        }

        if ($this->secret !== null && $this->confirmedAt === null) {
            return 'pending';
        }

        return 'disabled';
    }
}
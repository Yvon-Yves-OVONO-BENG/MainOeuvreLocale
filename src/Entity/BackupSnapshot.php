<?php

namespace App\Entity;

use App\Repository\BackupSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BackupSnapshotRepository::class)]
#[ORM\Table(name: 'backup_snapshot')]
class BackupSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $backupType = null;

    #[ORM\Column(length: 80)]
    private ?string $storageProvider = null;

    #[ORM\Column(length: 255)]
    private ?string $storagePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column(nullable: true)]
    private ?int $sizeBytes = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEncrypted = false;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $encryptionAlgorithm = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $retentionUntil = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBackupType(): ?string
    {
        return $this->backupType;
    }

    public function setBackupType(string $backupType): static
    {
        $this->backupType = $backupType;
        return $this;
    }

    public function getStorageProvider(): ?string
    {
        return $this->storageProvider;
    }

    public function setStorageProvider(string $storageProvider): static
    {
        $this->storageProvider = $storageProvider;
        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;
        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): static
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(?int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function setIsEncrypted(bool $isEncrypted): static
    {
        $this->isEncrypted = $isEncrypted;
        return $this;
    }

    public function getEncryptionAlgorithm(): ?string
    {
        return $this->encryptionAlgorithm;
    }

    public function setEncryptionAlgorithm(?string $encryptionAlgorithm): static
    {
        $this->encryptionAlgorithm = $encryptionAlgorithm;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getRetentionUntil(): ?\DateTimeImmutable
    {
        return $this->retentionUntil;
    }

    public function setRetentionUntil(?\DateTimeImmutable $retentionUntil): static
    {
        $this->retentionUntil = $retentionUntil;
        return $this;
    }
}
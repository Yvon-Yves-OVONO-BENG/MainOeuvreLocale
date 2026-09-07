<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'document_access_grant')]
#[ORM\UniqueConstraint(name: 'uniq_document_grant', columns: ['owner_id', 'viewer_id', 'document_type'])]
class DocumentAccessGrant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $viewer = null;
    #[ORM\Column(length: 20)]
    private string $documentType = 'cv';
    #[ORM\Column(length: 20)]
    private string $status = 'pending';
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;
    #[ORM\Column(options: ['default' => 0])]
    private int $accessCount = 0;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAccessAt = null;
    public function __construct() { $this->requestedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $v): static { $this->owner = $v; return $this; }
    public function getViewer(): ?User { return $this->viewer; }
    public function setViewer(?User $v): static { $this->viewer = $v; return $this; }
    public function getDocumentType(): string { return $this->documentType; }
    public function setDocumentType(string $v): static { $this->documentType = in_array($v, ['cv','cni'], true) ? $v : 'cv'; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getRequestedAt(): \DateTimeImmutable { return $this->requestedAt; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function setDecidedAt(?\DateTimeImmutable $v): static { $this->decidedAt = $v; return $this; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $v): static { $this->expiresAt = $v; return $this; }
    public function getAccessCount(): int { return $this->accessCount; }
    public function registerAccess(): static { ++$this->accessCount; $this->lastAccessAt = new \DateTimeImmutable(); return $this; }
    public function getLastAccessAt(): ?\DateTimeImmutable { return $this->lastAccessAt; }
    public function isUsable(): bool { return $this->status === 'approved' && ($this->expiresAt === null || $this->expiresAt > new \DateTimeImmutable()); }
}

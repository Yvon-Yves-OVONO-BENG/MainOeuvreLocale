<?php

namespace App\Entity;

use App\Repository\UserActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityLogRepository::class)]
#[ORM\Table(name: 'user_activity_log')]
class UserActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 80)]
    private string $event = 'unknown';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'json')]
    private array $meta = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getEvent(): string { return $this->event; }
    public function setEvent(string $event): self { $this->event = $event; return $this; }

    public function setIp(?string $ip): self { $this->ip = $ip; return $this; }
    public function getIp(): ?string { return $this->ip; }

    public function setUserAgent(?string $ua): self { $this->userAgent = $ua; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }

    public function setMeta(array $meta): self { $this->meta = $meta; return $this; }
    public function getMeta(): array { return $this->meta; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}
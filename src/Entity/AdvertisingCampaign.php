<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advertising_campaign')]
class AdvertisingCampaign
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AdvertisingSpace $space = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $advertiser = null;
    #[ORM\Column(length: 180)]
    private string $title = '';
    #[ORM\Column(length: 255)]
    private string $image = '';
    #[ORM\Column(length: 500)]
    private string $targetUrl = '';
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endsAt;
    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';
    #[ORM\Column(options: ['default' => 0])]
    private int $impressions = 0;
    #[ORM\Column(options: ['default' => 0])]
    private int $clicks = 0;
    public function __construct() { $this->startsAt = new \DateTimeImmutable(); $this->endsAt = new \DateTimeImmutable('+7 days'); }
    public function getId(): ?int { return $this->id; }
    public function getSpace(): ?AdvertisingSpace { return $this->space; }
    public function setSpace(?AdvertisingSpace $v): static { $this->space = $v; return $this; }
    public function getAdvertiser(): ?User { return $this->advertiser; }
    public function setAdvertiser(?User $v): static { $this->advertiser = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = trim($v); return $this; }
    public function getImage(): string { return $this->image; }
    public function setImage(string $v): static { $this->image = trim($v); return $this; }
    public function getTargetUrl(): string { return $this->targetUrl; }
    public function setTargetUrl(string $v): static { $this->targetUrl = trim($v); return $this; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(\DateTimeImmutable $v): static { $this->startsAt = $v; return $this; }
    public function getEndsAt(): \DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(\DateTimeImmutable $v): static { $this->endsAt = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getImpressions(): int { return $this->impressions; }
    public function incrementImpressions(): static { ++$this->impressions; return $this; }
    public function getClicks(): int { return $this->clicks; }
    public function incrementClicks(): static { ++$this->clicks; return $this; }
}

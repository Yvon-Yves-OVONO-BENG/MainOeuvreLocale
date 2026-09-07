<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'advertising_space')]
class AdvertisingSpace
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 80, unique: true)]
    private string $code = '';
    #[ORM\Column(length: 160)]
    private string $name = '';
    #[ORM\Column(length: 30)]
    private string $position = 'home';
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 0])]
    private string $dailyPrice = '0';
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;
    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): static { $this->code = trim($v); return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = trim($v); return $this; }
    public function getPosition(): string { return $this->position; }
    public function setPosition(string $v): static { $this->position = trim($v); return $this; }
    public function getDailyPrice(): string { return $this->dailyPrice; }
    public function setDailyPrice(string $v): static { $this->dailyPrice = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
}

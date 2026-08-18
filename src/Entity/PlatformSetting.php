<?php

namespace App\Entity;

use App\Repository\PlatformSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformSettingRepository::class)]
#[ORM\Table(name: 'platform_setting')]
class PlatformSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $maintenanceEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $maintenanceTitle = 'Maintenance en cours';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $maintenanceMessage = 'Nous revenons très bientôt.';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isMaintenanceEnabled(): bool
    {
        return $this->maintenanceEnabled;
    }

    public function setMaintenanceEnabled(bool $maintenanceEnabled): self
    {
        $this->maintenanceEnabled = $maintenanceEnabled;
        return $this;
    }

    public function getMaintenanceTitle(): ?string
    {
        return $this->maintenanceTitle;
    }

    public function setMaintenanceTitle(?string $maintenanceTitle): self
    {
        $this->maintenanceTitle = $maintenanceTitle;
        return $this;
    }

    public function getMaintenanceMessage(): ?string
    {
        return $this->maintenanceMessage;
    }

    public function setMaintenanceMessage(?string $maintenanceMessage): self
    {
        $this->maintenanceMessage = $maintenanceMessage;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
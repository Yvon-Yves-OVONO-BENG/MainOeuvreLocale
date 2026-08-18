<?php

namespace App\Entity;

use App\Repository\UserSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSettingRepository::class)]
#[ORM\Table(name: 'user_setting')]
#[ORM\UniqueConstraint(name: 'uniq_user_setting_user', columns: ['user_id'])]
class UserSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'json')]
    private array $notifications = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->notifications = [
            'channels' => [
                'email' => ['enabled' => true],
                'sms' => ['enabled' => false],
                'whatsapp' => ['enabled' => false],
            ],
            'events' => [
                'new_application' => true,
                'new_message' => true,
                'job_expiring' => true,
            ],
            'digest' => [
                'enabled' => false,
                'frequency' => 'weekly', // daily|weekly
            ],
        ];
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getNotifications(): array { return $this->notifications; }
    public function setNotifications(array $notifications): self { $this->notifications = $notifications; return $this; }

    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
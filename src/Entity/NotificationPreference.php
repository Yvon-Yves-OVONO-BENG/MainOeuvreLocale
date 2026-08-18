<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_preference')]
#[ORM\UniqueConstraint(name: 'uniq_notification_user_channel', columns: ['user_identifier', 'channel'])]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180)]
    public string $userIdentifier;

    #[ORM\Column(length: 80)]
    public string $channel = 'chat';

    #[ORM\Column]
    public bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $disabledAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct(string $userIdentifier, string $channel = 'chat')
    {
        $this->userIdentifier = $userIdentifier;
        $this->channel = $channel;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->disabledAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->disabledAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toggle(): void
    {
        if ($this->enabled) {
            $this->disable();
            return;
        }

        $this->enable();
    }
}
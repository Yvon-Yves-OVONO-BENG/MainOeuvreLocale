<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'message_sound_preference')]
#[ORM\UniqueConstraint(name: 'uniq_message_sound_preference_user', columns: ['user_identifier'])]
class MessageSoundPreference
{
    public const SOUND_SOFT = 'soft';
    public const SOUND_CRYSTAL = 'crystal';
    public const SOUND_BUBBLE = 'bubble';
    public const SOUND_DING = 'ding';
    public const SOUND_SUCCESS = 'success';
    public const SOUND_ALERT = 'alert';
    public const SOUND_POP = 'pop';
    public const SOUND_PULSE = 'pulse';
    public const SOUND_SOFT_BELL = 'soft_bell';
    public const SOUND_DIGITAL_DROP = 'digital_drop';
    public const SOUND_SILENT = 'silent';

    /** Dix sonneries sélectionnables + le mode silencieux. */
    public const ALLOWED_SOUNDS = [
        self::SOUND_SOFT,
        self::SOUND_CRYSTAL,
        self::SOUND_BUBBLE,
        self::SOUND_DING,
        self::SOUND_SUCCESS,
        self::SOUND_ALERT,
        self::SOUND_POP,
        self::SOUND_PULSE,
        self::SOUND_SOFT_BELL,
        self::SOUND_DIGITAL_DROP,
        self::SOUND_SILENT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_identifier', length: 180)]
    private string $userIdentifier;

    #[ORM\Column(length: 40)]
    private string $sound = self::SOUND_SOFT;

    #[ORM\Column]
    private bool $enabled = true;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;
        $this->touch();

        return $this;
    }

    public function getSound(): string
    {
        return $this->sound;
    }

    public function setSound(string $sound): self
    {
        if (!in_array($sound, self::ALLOWED_SOUNDS, true)) {
            $sound = self::SOUND_SOFT;
        }

        $this->sound = $sound;
        $this->touch();

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
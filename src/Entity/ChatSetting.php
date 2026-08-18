<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_setting')]
class ChatSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    public string $keyName = 'default';

    #[ORM\Column]
    public bool $chatEnabled = true;

    #[ORM\Column(length: 120)]
    public string $chatName = 'Assistance Main d’œuvre locale';

    #[ORM\Column(type: 'text')]
    public string $welcomeMessage = 'Bonjour 👋 Comment pouvons-nous vous aider aujourd’hui ?';

    #[ORM\Column(length: 40)]
    public string $defaultDepartment = 'support';

    #[ORM\Column(length: 20)]
    public string $primaryColor = '#2563eb';

    #[ORM\Column(length: 40)]
    public string $chatPosition = 'bottom_right';

    #[ORM\Column(length: 20)]
    public string $chatTheme = 'light';

    #[ORM\Column]
    public bool $showAvatar = true;

    #[ORM\Column(length: 5)]
    public string $openTime = '08:00';

    #[ORM\Column(length: 5)]
    public string $closeTime = '18:00';

    #[ORM\Column(length: 80)]
    public string $timezone = 'Africa/Douala';

    #[ORM\Column(type: 'text')]
    public string $offlineMessage = 'Nous sommes actuellement indisponibles. Laissez votre message, nous vous répondrons rapidement.';

    #[ORM\Column]
    public bool $autoOpen = false;

    #[ORM\Column]
    public int $autoOpenDelay = 8;

    #[ORM\Column]
    public bool $requireContact = true;

    #[ORM\Column]
    public bool $humanTransfer = true;

    #[ORM\Column(length: 180, nullable: true)]
    public ?string $notificationEmail = 'support@example.com';

    #[ORM\Column]
    public bool $notifyNewMessage = true;

    #[ORM\Column]
    public bool $dailySummary = true;

    #[ORM\Column]
    public bool $requireConsent = true;

    #[ORM\Column]
    public int $retentionDays = 90;

    #[ORM\Column(type: 'text')]
    public string $consentText = 'En utilisant ce chat, vous acceptez que vos messages soient traités afin de répondre à votre demande.';

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
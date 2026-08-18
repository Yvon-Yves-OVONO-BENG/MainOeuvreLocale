<?php

namespace App\Service;

use App\Entity\NotificationPreference;
use Doctrine\ORM\EntityManagerInterface;

class ChatNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function canNotify(string $userIdentifier, string $channel = 'chat'): bool
    {
        $preference = $this->em->getRepository(NotificationPreference::class)->findOneBy([
            'userIdentifier' => $userIdentifier,
            'channel' => $channel,
        ]);

        if (!$preference) {
            return true;
        }

        return $preference->enabled;
    }

    public function notify(string $userIdentifier, string $message): void
    {
        if (!$this->canNotify($userIdentifier)) {
            return;
        }

        // Ici ton vrai code d’envoi :
        // email, notification interne, websocket, push, etc.
    }
}
<?php

namespace App\Service;

use App\Entity\ChatBlockedUser;
use App\Entity\ChatBlockPreference;
use Doctrine\ORM\EntityManagerInterface;

class ChatBlockService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function isUserBlocked(string $receiverIdentifier, string $senderIdentifier): bool
    {
        $blockedUser = $this->em->getRepository(ChatBlockedUser::class)->findOneBy([
            'blockerIdentifier' => $receiverIdentifier,
            'blockedIdentifier' => $senderIdentifier,
            'active' => true,
        ]);

        return $blockedUser !== null;
    }

    public function getPreference(string $userIdentifier): ?ChatBlockPreference
    {
        return $this->em->getRepository(ChatBlockPreference::class)->findOneBy([
            'userIdentifier' => $userIdentifier,
        ]);
    }

    public function canReceiveText(string $receiverIdentifier, string $senderIdentifier, string $content): bool
    {
        if ($this->isUserBlocked($receiverIdentifier, $senderIdentifier)) {
            return false;
        }

        $preference = $this->getPreference($receiverIdentifier);

        if (!$preference) {
            return true;
        }

        if ($preference->isBlockLinks() && preg_match('/https?:\/\/|www\./i', $content)) {
            return false;
        }

        return true;
    }

    public function canReceiveFile(string $receiverIdentifier, string $senderIdentifier): bool
    {
        if ($this->isUserBlocked($receiverIdentifier, $senderIdentifier)) {
            return false;
        }

        $preference = $this->getPreference($receiverIdentifier);

        return !$preference || !$preference->isBlockFiles();
    }

    public function canReceiveCall(string $receiverIdentifier, string $senderIdentifier): bool
    {
        if ($this->isUserBlocked($receiverIdentifier, $senderIdentifier)) {
            return false;
        }

        $preference = $this->getPreference($receiverIdentifier);

        return !$preference || !$preference->isBlockCalls();
    }
}
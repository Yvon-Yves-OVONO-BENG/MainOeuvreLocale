<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ConversationVoter extends Voter
{
    public const VIEW = 'CONVERSATION_VIEW';
    public const SEND = 'CONVERSATION_SEND';
    public const MANAGE = 'CONVERSATION_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::SEND, self::MANAGE], true)
            && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || !$subject instanceof Conversation) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $isParticipant = $subject->getParticipantA()?->getId() === $user->getId()
            || $subject->getParticipantB()?->getId() === $user->getId();

        return match ($attribute) {
            self::VIEW, self::SEND, self::MANAGE => $isParticipant,
            default => false,
        };
    }
}

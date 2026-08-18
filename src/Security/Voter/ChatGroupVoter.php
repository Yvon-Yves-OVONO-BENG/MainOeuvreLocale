<?php

namespace App\Security\Voter;

use App\Entity\ChatGroup;
use App\Entity\ChatGroupMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ChatGroupVoter extends Voter
{
    public const VIEW = 'CHAT_GROUP_VIEW';
    public const SEND = 'CHAT_GROUP_SEND';
    public const MANAGE = 'CHAT_GROUP_MANAGE';
    public const MODERATE = 'CHAT_GROUP_MODERATE';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::SEND, self::MANAGE, self::MODERATE], true)
            && $subject instanceof ChatGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || !$subject instanceof ChatGroup) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if ($subject->getOwner()?->getId() === $user->getId()) {
            return true;
        }

        $member = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $subject,
            'user' => $user,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        if (!$member instanceof ChatGroupMember) {
            return $attribute === self::VIEW && $subject->getVisibility() === ChatGroup::VISIBILITY_PUBLIC;
        }

        return match ($attribute) {
            self::VIEW, self::SEND => true,
            self::MANAGE, self::MODERATE => in_array($member->getRole(), [ChatGroupMember::ROLE_OWNER, ChatGroupMember::ROLE_ADMIN], true),
            default => false,
        };
    }
}

<?php

namespace App\Service\Chat;

use App\Entity\ChatGroup;
use App\Entity\ChatGroupMember;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ChatAccessGuard
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function assertPrivateParticipant(Conversation $conversation, User $user): void
    {
        $a = $conversation->getParticipantA();
        $b = $conversation->getParticipantB();

        if ($a?->getId() !== $user->getId() && $b?->getId() !== $user->getId()) {
            throw new AccessDeniedException('Accès refusé à cette conversation.');
        }
    }

    public function otherParticipant(Conversation $conversation, User $user): ?User
    {
        if (method_exists($conversation, 'getOtherParticipant')) {
            return $conversation->getOtherParticipant($user);
        }

        return $conversation->getParticipantA()?->getId() === $user->getId()
            ? $conversation->getParticipantB()
            : $conversation->getParticipantA();
    }

    public function isGroupMember(ChatGroup $group, User $user): bool
    {
        if ($group->getOwner()?->getId() === $user->getId()) {
            return true;
        }

        return null !== $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $user,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);
    }

    public function assertGroupMember(ChatGroup $group, User $user): void
    {
        if (!$this->isGroupMember($group, $user)) {
            throw new AccessDeniedException('Vous n’êtes pas membre de ce groupe.');
        }
    }

    public function canManageGroup(ChatGroup $group, User $user): bool
    {
        if ($group->getOwner()?->getId() === $user->getId()) {
            return true;
        }

        $member = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $user,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);

        return $member instanceof ChatGroupMember
            && in_array($member->getRole(), [ChatGroupMember::ROLE_OWNER, ChatGroupMember::ROLE_ADMIN], true);
    }

    public function assertGroupManager(ChatGroup $group, User $user): void
    {
        if (!$this->canManageGroup($group, $user)) {
            throw new AccessDeniedException('Action réservée au créateur ou aux admins du groupe.');
        }
    }
}

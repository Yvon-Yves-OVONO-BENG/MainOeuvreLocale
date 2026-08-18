<?php

namespace App\Service;

use App\Entity\ChatGroup;
use App\Entity\ChatGroupMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChatGroupAccessService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function getMembership(ChatGroup $group, User $user): ?ChatGroupMember
    {
        return $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $user,
            'status' => ChatGroupMember::STATUS_ACTIVE,
        ]);
    }

    public function assertMember(ChatGroup $group, User $user): ChatGroupMember
    {
        $member = $this->getMembership($group, $user);

        if ($member) {
            return $member;
        }

        if ($this->isOwner($group, $user)) {
            return $this->ensureOwnerMembership($group, $user);
        }

        throw new AccessDeniedHttpException('Vous n’êtes pas membre de ce groupe.');
    }

    public function ensureOwnerMembership(ChatGroup $group, User $user): ChatGroupMember
    {
        $member = $this->em->getRepository(ChatGroupMember::class)->findOneBy([
            'chatGroup' => $group,
            'user' => $user,
        ]);

        if (!$member) {
            $member = (new ChatGroupMember())
                ->setChatGroup($group)
                ->setUser($user);

            $this->em->persist($member);
        }

        $member
            ->setRole(ChatGroupMember::ROLE_OWNER)
            ->setStatus(ChatGroupMember::STATUS_ACTIVE);

        $this->em->flush();

        return $member;
    }

    public function isOwner(ChatGroup $group, User $user): bool
    {
        return $group->getOwner()?->getId() === $user->getId();
    }

    public function isAdminOrOwner(ChatGroup $group, User $user): bool
    {
        if ($this->isOwner($group, $user)) {
            return true;
        }

        $member = $this->getMembership($group, $user);

        return $member && in_array($member->getRole(), [
            ChatGroupMember::ROLE_OWNER,
            ChatGroupMember::ROLE_ADMIN,
        ], true);
    }

    public function assertAdminOrOwner(ChatGroup $group, User $user): void
    {
        if (!$this->isAdminOrOwner($group, $user)) {
            throw new AccessDeniedHttpException('Action réservée au créateur ou aux admins du groupe.');
        }
    }
}
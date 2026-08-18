<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PresenceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function requireManagedUser(?object $user): User
    {
        if (!$user instanceof User || !$user->getId()) {
            throw new AccessDeniedException();
        }

        $managed = $this->userRepository->find($user->getId());

        if (!$managed instanceof User) {
            throw new AccessDeniedException();
        }

        return $managed;
    }

    public function heartbeat(User $user): array
    {
        $now = new \DateTimeImmutable();

        $user->setIsOnline(true);
        $user->setLastSeenAt($now);
        $user->setLastDisconnectedAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [
            'ok' => true,
            'userId' => $user->getId(),
            'status' => $user->getPresenceStatus(),
            'isOnline' => $user->isOnline(),
            'lastSeenAt' => $user->getLastSeenAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function offline(User $user): array
    {
        $now = new \DateTimeImmutable();

        $user->setIsOnline(false);
        $user->setLastDisconnectedAt($now);
        $user->setLastSeenAt($now);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [
            'ok' => true,
            'userId' => $user->getId(),
            'status' => $user->getPresenceStatus(),
            'isOnline' => $user->isOnline(),
            'lastSeenAt' => $user->getLastSeenAt()?->format('Y-m-d H:i:s'),
            'lastDisconnectedAt' => $user->getLastDisconnectedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function getUserStatus(User $user): array
    {
        return [
            'ok' => true,
            'userId' => $user->getId(),
            'presence' => $user->getPresenceStatus(),
            'isOnline' => $user->isOnline(),
            'lastSeenAt' => $user->getLastSeenAt()?->format('Y-m-d H:i:s'),
            'lastDisconnectedAt' => $user->getLastDisconnectedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function applyNoCacheHeaders(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
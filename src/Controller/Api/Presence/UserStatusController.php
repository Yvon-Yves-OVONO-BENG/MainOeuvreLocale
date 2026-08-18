<?php

namespace App\Controller\Api\Presence;

use App\Entity\User;
use App\Service\PresenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/presence', name: 'api_presence_')]
#[IsGranted('ROLE_USER')]
final class UserStatusController extends AbstractController
{
    #[Route('/user/{id}', name: 'user_status', methods: ['GET'])]
    public function __invoke(User $user, PresenceService $presenceService): JsonResponse
    {
        return $this->json(
            $presenceService->getUserStatus($user)
        );
    }
}
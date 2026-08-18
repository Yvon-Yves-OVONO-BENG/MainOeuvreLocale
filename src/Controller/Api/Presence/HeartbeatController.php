<?php

namespace App\Controller\Api\Presence;

use App\Service\PresenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/presence', name: 'api_presence_')]
final class HeartbeatController extends AbstractController
{
    #[Route('/heartbeat', name: 'heartbeat', methods: ['POST'])]
    public function __invoke(PresenceService $presenceService): JsonResponse
    {
        try {
            $me = $presenceService->requireManagedUser($this->getUser());

            return $presenceService->applyNoCacheHeaders(
                $this->json($presenceService->heartbeat($me))
            );
        } catch (AccessDeniedException) {
            return $this->json([
                'ok' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }
    }
}
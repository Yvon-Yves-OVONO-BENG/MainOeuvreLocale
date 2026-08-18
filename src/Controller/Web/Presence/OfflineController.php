<?php

namespace App\Controller\Web\Presence;

use App\Service\PresenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/presence', name: 'presence_')]
final class OfflineController extends AbstractController
{
    #[Route('/offline', name: 'offline', methods: ['POST'])]
    public function __invoke(PresenceService $presenceService): JsonResponse
    {
        try {
            $me = $presenceService->requireManagedUser($this->getUser());

            return $presenceService->applyNoCacheHeaders(
                $this->json($presenceService->offline($me))
            );
        } catch (AccessDeniedException) {
            return $this->json([
                'ok' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }
    }
}
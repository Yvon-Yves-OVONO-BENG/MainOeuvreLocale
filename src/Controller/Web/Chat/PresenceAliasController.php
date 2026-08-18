<?php

namespace App\Controller\Web\Chat;

use App\Entity\User;
use App\Service\PresenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PresenceAliasController extends AbstractController
{
    #[Route('/presence/heartbeat', name: 'presence_heartbeat', methods: ['POST'])]
    public function heartbeat(PresenceService $presenceService): JsonResponse
    {
        try {
            $me = $presenceService->requireManagedUser($this->getUser());

            return $presenceService->applyNoCacheHeaders(
                $this->json($presenceService->heartbeat($me))
            );
        } catch (AccessDeniedException) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }
    }

    #[Route('/presence/offline', name: 'presence_offline', methods: ['POST'])]
    public function offline(PresenceService $presenceService): JsonResponse
    {
        try {
            $me = $presenceService->requireManagedUser($this->getUser());

            return $presenceService->applyNoCacheHeaders(
                $this->json($presenceService->offline($me))
            );
        } catch (AccessDeniedException) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }
    }

    #[Route('/presence/user/{id}', name: 'presence_user_status', methods: ['GET'])]
    public function status(User $user, PresenceService $presenceService): JsonResponse
    {
        return $presenceService->applyNoCacheHeaders(
            $this->json($presenceService->getUserStatus($user))
        );
    }
}

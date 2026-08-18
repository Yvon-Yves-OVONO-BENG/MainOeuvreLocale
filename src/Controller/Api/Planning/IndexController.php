<?php

namespace App\Controller\Api\Planning;

use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
final class IndexController extends AbstractController
{
    #[Route('/api/planning', name: 'api_planning', methods: ['GET'])]
    public function __invoke(PlanningService $planningService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $planningService->requireUser($this->getUser());

        return $this->json(
            $planningService->getApiPlanningPayload($me)
        );
    }
}
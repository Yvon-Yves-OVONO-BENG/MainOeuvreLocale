<?php

namespace App\Controller\Api\Boost;

use App\Service\BoostPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PlansController extends AbstractController
{
    #[Route('/api/boost-plan', name: 'api_boost_plans', methods: ['GET'])]
    public function __invoke(BoostPlanService $boostPlanService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER', 'Accès refusé. Connectez-vous');

        return $this->json(
            $boostPlanService->getApiPlansPayload()
        );
    }
}
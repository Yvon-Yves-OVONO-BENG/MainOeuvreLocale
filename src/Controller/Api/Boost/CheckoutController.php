<?php

namespace App\Controller\Api\Boost;

use App\Service\BoostPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    #[Route('/api/payment-boost/{plan}', name: 'api_payment_boost', requirements: ['plan' => 'premium|gold|vip'], methods: ['GET'])]
    public function __invoke(string $plan, BoostPlanService $boostPlanService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_TALENT');

        try {
            return $this->json(
                $boostPlanService->getApiCheckoutPayload($plan)
            );
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }
}
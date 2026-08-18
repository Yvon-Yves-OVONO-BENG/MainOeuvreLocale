<?php

namespace App\Controller\Api\Boost;

use App\Service\BoostCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostCheckoutController extends AbstractController
{
    #[Route('/api/boost-checkout/{method}', name: 'api_boost_checkout', methods: ['GET'])]
    public function __invoke(string $method, BoostCheckoutService $boostCheckoutService): JsonResponse
    {
        try {
            return $this->json(
                $boostCheckoutService->getApiCheckoutPayload($method)
            );
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }
}
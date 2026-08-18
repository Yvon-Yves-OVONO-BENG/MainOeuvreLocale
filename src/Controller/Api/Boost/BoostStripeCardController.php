<?php

namespace App\Controller\Api\Boost;

use App\Service\BoostStripeCardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostStripeCardController extends AbstractController
{
    #[Route('/api/boost-card/{plan}/{montant}', name: 'api_boost_stripe_card', methods: ['GET'])]
    public function __invoke(
        string $plan,
        int $montant,
        BoostStripeCardService $boostStripeCardService
    ): JsonResponse {
        try {
            return $this->json(
                $boostStripeCardService->getApiCheckoutPayload($plan, $montant)
            );
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        } catch (BadRequestHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}
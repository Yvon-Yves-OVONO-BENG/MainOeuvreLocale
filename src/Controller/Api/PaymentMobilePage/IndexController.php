<?php

namespace App\Controller\Api\PaymentMobilePage;

use App\Service\PaymentMobilePageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class IndexController extends AbstractController
{
    #[Route('/api/payment-boost/{plan}/{montant}/mobile/{method}', name: 'api_payment_boost_mobile_page', methods: ['GET'])]
    public function __invoke(
        string $plan,
        int $montant,
        string $method,
        PaymentMobilePageService $paymentMobilePageService
    ): JsonResponse {
        try {
            return $this->json(
                $paymentMobilePageService->getApiPayload($plan, $montant, $method)
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
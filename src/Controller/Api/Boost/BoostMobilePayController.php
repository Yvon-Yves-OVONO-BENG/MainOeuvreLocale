<?php

namespace App\Controller\Api\Boost;

use App\Service\BoostMobilePayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostMobilePayController extends AbstractController
{
    #[Route('/api/boost-mobile-pay/{plan}/{montant}', name: 'api_boost_mobile_pay', methods: ['POST'])]
    public function __invoke(
        Request $request,
        string $plan,
        int $montant,
        BoostMobilePayService $boostMobilePayService
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        $method = $payload['method'] ?? $request->request->get('method');
        $phone = $payload['phone'] ?? $request->request->get('phone');

        try {
            return $this->json(
                $boostMobilePayService->getApiPayload($plan, $montant, $method, $phone)
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
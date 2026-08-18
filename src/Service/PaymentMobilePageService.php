<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PaymentMobilePageService
{
    public function __construct(
        private readonly BoostPlanService $boostPlanService
    ) {
    }

    public function getPageData(string $plan, int|string $montant, string $method): array
    {
        $planData = $this->boostPlanService->getPlanOrFail($plan);
        $amount = (int) $montant;

        if ($amount <= 0) {
            throw new BadRequestHttpException('Montant invalide.');
        }

        if ($amount !== (int) $planData['price']) {
            throw new BadRequestHttpException('Le montant ne correspond pas au plan sélectionné.');
        }

        $normalizedMethod = $this->normalizeMethod($method);

        if (!in_array($normalizedMethod, ['OM', 'MOMO'], true)) {
            throw new NotFoundHttpException('Méthode invalide');
        }

        return [
            'plan' => $plan,
            'planData' => $planData,
            'montant' => $amount,
            'method' => $normalizedMethod,
        ];
    }

    public function getApiPayload(string $plan, int|string $montant, string $method): array
    {
        $data = $this->getPageData($plan, $montant, $method);

        return [
            'ok' => true,
            'plan' => $data['plan'],
            'planData' => $data['planData'],
            'montant' => $data['montant'],
            'method' => $data['method'],
        ];
    }

    private function normalizeMethod(string $method): string
    {
        $value = strtoupper(trim($method));

        return match ($value) {
            'OM', 'ORANGE', 'ORANGE_MONEY', 'ORANGE-MONEY', 'ORANGEMONEY' => 'OM',
            'MOMO', 'MTN', 'MTN_MOMO', 'MTN-MOMO', 'MOBILE_MONEY', 'MOBILE-MONEY' => 'MOMO',
            default => $value,
        };
    }
}
<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BoostMobilePayService
{
    public function __construct(
        private readonly BoostPlanService $boostPlanService
    ) {
    }

    public function process(string $planKey, int|string $montant, ?string $method, ?string $phone): array
    {
        $plan = $this->boostPlanService->getPlanOrFail($planKey);

        $amount = (int) $montant;
        if ($amount <= 0) {
            throw new BadRequestHttpException('Montant invalide.');
        }

        if ($amount !== (int) $plan['price']) {
            throw new BadRequestHttpException('Le montant ne correspond pas au plan sélectionné.');
        }

        $normalizedMethod = $this->normalizeMethod((string) $method);
        if (!in_array($normalizedMethod, ['om', 'momo'], true)) {
            throw new BadRequestHttpException('Méthode de paiement invalide.');
        }

        $normalizedPhone = $this->normalizePhone((string) $phone);
        if (strlen($normalizedPhone) !== 9) {
            throw new BadRequestHttpException('Numéro invalide (9 chiffres attendus).');
        }

        return [
            'ok' => true,
            'planKey' => $planKey,
            'plan' => $plan,
            'amount' => $amount,
            'method' => $normalizedMethod,
            'phone' => $normalizedPhone,
            'message' => strtoupper($normalizedMethod) . ' : demande envoyée au +237 ' . $normalizedPhone . '. Confirmez sur votre téléphone.',
        ];
    }

    public function getApiPayload(string $planKey, int|string $montant, ?string $method, ?string $phone): array
    {
        $result = $this->process($planKey, $montant, $method, $phone);

        return [
            'ok' => true,
            'message' => $result['message'],
            'payment' => [
                'planKey' => $result['planKey'],
                'plan' => $result['plan'],
                'amount' => $result['amount'],
                'method' => $result['method'],
                'phone' => $result['phone'],
                'countryCode' => '+237',
                'providerStatus' => 'pending',
            ],
        ];
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'om', 'orange-money', 'orange_money', 'orangemoney' => 'om',
            'momo', 'mobile-money', 'mobile_money', 'mtn-momo', 'mtn_momo', 'mtnmomo' => 'momo',
            default => $method,
        };
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BoostStripeCardService
{
    public function __construct(
        private readonly BoostPlanService $boostPlanService,
        private readonly string $stripePublicKey
    ) {
    }

    public function getCheckoutPageData(string $planKey, int|string $montant): array
    {
        $plan = $this->boostPlanService->getPlanOrFail($planKey);
        $amount = (int) $montant;

        if ($amount <= 0) {
            throw new BadRequestHttpException('Montant invalide.');
        }

        if ($amount !== (int) $plan['price']) {
            throw new BadRequestHttpException('Le montant ne correspond pas au plan sélectionné.');
        }

        if (trim($this->stripePublicKey) === '') {
            throw new BadRequestHttpException('Clé publique Stripe introuvable.');
        }

        return [
            'stripePublicKey' => $this->stripePublicKey,
            'plan' => $planKey,
            'montant' => $amount,
            'planData' => $plan,
        ];
    }

    public function getApiCheckoutPayload(string $planKey, int|string $montant): array
    {
        $data = $this->getCheckoutPageData($planKey, $montant);

        return [
            'ok' => true,
            'stripePublicKey' => $data['stripePublicKey'],
            'plan' => $data['plan'],
            'montant' => $data['montant'],
            'planData' => $data['planData'],
            'paymentMethod' => 'stripe_card',
        ];
    }

    /** Retourne la clé publique Stripe après avoir vérifié sa configuration. */
    public function getPublicKey(): string
    {
        if (trim($this->stripePublicKey) === '') {
            throw new BadRequestHttpException('Clé publique Stripe introuvable.');
        }

        return $this->stripePublicKey;
    }
}

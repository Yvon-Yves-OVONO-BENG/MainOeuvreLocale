<?php

namespace App\Service;

use App\Entity\User;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BoostStripeIntentService
{
    public function __construct(
        private readonly BoostPlanService $boostPlanService,
        private readonly StripeClient $stripeClient,
        private readonly string $currency = 'xaf'
    ) {
    }

    public function createIntent(string $planKey, int|string $montant, ?User $user = null): array
    {
        $plan = $this->boostPlanService->getPlanOrFail($planKey);
        $amount = (int) $montant;

        if ($amount <= 0) {
            throw new BadRequestHttpException('Montant invalide.');
        }

        if ($amount !== (int) $plan['price']) {
            throw new BadRequestHttpException('Le montant ne correspond pas au plan sélectionné.');
        }

        try {
            $intent = $this->stripeClient->paymentIntents->create([
                'amount' => $amount,
                'currency' => $this->currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => array_filter([
                    'plan_key' => $planKey,
                    'plan_name' => (string) ($plan['name'] ?? ''),
                    'user_id' => $user?->getId(),
                    'user_email' => $user?->getEmail(),
                ], static fn ($value) => $value !== null && $value !== ''),
            ]);
        } catch (ApiErrorException $exception) {
            throw new BadRequestHttpException(
                'Impossible de créer l’intention de paiement Stripe : ' . $exception->getMessage()
            );
        }

        return [
            'ok' => true,
            'clientSecret' => $intent->client_secret,
            'paymentIntentId' => $intent->id,
            'planKey' => $planKey,
            'amount' => $amount,
            'currency' => $this->currency,
            'plan' => $plan,
        ];
    }
}
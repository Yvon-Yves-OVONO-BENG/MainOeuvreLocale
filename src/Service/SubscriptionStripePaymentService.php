<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\PlanDuration;
use App\Entity\User;
use App\Entity\Profession;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SubscriptionStripePaymentService
{
    public function __construct(
        private readonly StripeClient $stripeClient,
        private readonly string $currency = 'xaf'
    ) {
    }

    /** Crée une intention Stripe à partir du prix serveur de la durée sélectionnée. */
    public function createIntent(Plan $plan, PlanDuration $duration, User $user, ?Profession $profession = null): array
    {
        if ($duration->getPlan()?->getId() !== $plan->getId()) {
            throw new BadRequestHttpException('La durée ne correspond pas à cette offre.');
        }

        if ($plan->getSlug() === 'pro' && !$profession) {
            throw new BadRequestHttpException('La profession du ticket est obligatoire.');
        }

        $amount = $plan->getSlug() === 'pro' ? ContactTicketManager::PRICE : (int) $duration->getPrice();
        if ($amount <= 0) {
            throw new BadRequestHttpException('Montant d’abonnement invalide.');
        }

        try {
            $intent = $this->stripeClient->paymentIntents->create([
                'amount' => $amount,
                'currency' => $this->currency,
                'automatic_payment_methods' => ['enabled' => true],
                'description' => $plan->getSlug() === 'pro'
                    ? sprintf('Ticket 3 contacts - %s', $profession?->getProfession())
                    : sprintf('Abonnement %s - %s', $plan->getName(), $duration->getLabel()),
                'metadata' => [
                    'payment_kind' => $plan->getSlug() === 'pro' ? 'contact_ticket' : 'subscription',
                    'user_id' => (string) $user->getId(),
                    'plan_id' => (string) $plan->getId(),
                    'duration_id' => (string) $duration->getId(),
                    'profession_id' => (string) ($profession?->getId() ?? ''),
                ],
            ]);
        } catch (ApiErrorException $exception) {
            throw new BadRequestHttpException('Impossible d’initialiser le paiement sécurisé.', $exception);
        }

        return [
            'ok' => true,
            'clientSecret' => $intent->client_secret,
            'paymentIntentId' => $intent->id,
        ];
    }

    /** Vérifie auprès de Stripe le statut, le montant et le propriétaire du paiement. */
    public function verifySucceededPayment(
        string $paymentIntentId,
        Plan $plan,
        PlanDuration $duration,
        User $user,
        ?Profession $profession = null
    ): array {
        if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $paymentIntentId)) {
            return ['success' => false, 'message' => 'Référence de paiement invalide.'];
        }

        try {
            $intent = $this->stripeClient->paymentIntents->retrieve($paymentIntentId, []);
        } catch (ApiErrorException) {
            return ['success' => false, 'message' => 'Paiement introuvable auprès du prestataire.'];
        }

        $metadata = $intent->metadata;
        $expectedAmount = $plan->getSlug() === 'pro' ? ContactTicketManager::PRICE : (int) $duration->getPrice();
        $expectedKind = $plan->getSlug() === 'pro' ? 'contact_ticket' : 'subscription';

        $isValid = $intent->status === 'succeeded'
            && (int) $intent->amount_received === $expectedAmount
            && strtolower((string) $intent->currency) === $this->currency
            && (string) ($metadata['payment_kind'] ?? '') === $expectedKind
            && (string) ($metadata['user_id'] ?? '') === (string) $user->getId()
            && (string) ($metadata['plan_id'] ?? '') === (string) $plan->getId()
            && (string) ($metadata['duration_id'] ?? '') === (string) $duration->getId()
            && ($plan->getSlug() !== 'pro'
                || ($profession && (string) ($metadata['profession_id'] ?? '') === (string) $profession->getId()));

        if (!$isValid) {
            return ['success' => false, 'message' => 'Le paiement n’est pas confirmé ou ne correspond pas à la commande.'];
        }

        return ['success' => true, 'transaction_id' => $intent->id];
    }
}

<?php

namespace App\Service;

class PaymentService
{
    public function __construct(
        private readonly SubscriptionStripePaymentService $stripePaymentService
    ) {}

    public function processPayment(array $data): array
    {
        $method = $data['method'] ?? 'card';
        
        switch ($method) {
            case 'card':
                return $this->processCardPayment($data);
            case 'orange':
                return $this->processOrangeMoney($data);
            case 'mtn':
                return $this->processMtnMoney($data);
            default:
                return ['success' => false, 'message' => 'Méthode de paiement non supportée'];
        }
    }

    private function processCardPayment(array $data): array
    {
        $token = trim((string) ($data['token'] ?? ''));
        $plan = $data['plan'] ?? null;
        $duration = $data['duration'] ?? null;
        $user = $data['user'] ?? null;
        $profession = $data['profession'] ?? null;

        if (!$plan || !$duration || !$user || $token === '') {
            return ['success' => false, 'message' => 'Confirmation de paiement manquante.'];
        }

        return $this->stripePaymentService->verifySucceededPayment(
            $token,
            $plan,
            $duration,
            $user,
            $profession
        );
    }

    private function processOrangeMoney(array $data): array
    {
        return [
            'success' => false,
            'message' => 'Orange Money est indisponible tant que le connecteur opérateur n’est pas configuré.',
        ];
    }

    private function processMtnMoney(array $data): array
    {
        return [
            'success' => false,
            'message' => 'MTN Mobile Money est indisponible tant que le connecteur opérateur n’est pas configuré.',
        ];
    }
}

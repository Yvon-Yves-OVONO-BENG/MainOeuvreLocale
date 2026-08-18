<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BoostCheckoutService
{
    public function getAvailableMethods(): array
    {
        return [
            'om' => [
                'key' => 'om',
                'name' => 'Orange Money',
                'shortName' => 'OM',
                'icon' => 'fa-mobile-screen-button',
                'description' => 'Paiement via Orange Money',
                'currency' => 'XAF',
                'enabled' => true,
            ],
            'momo' => [
                'key' => 'momo',
                'name' => 'MTN Mobile Money',
                'shortName' => 'MoMo',
                'icon' => 'fa-wallet',
                'description' => 'Paiement via MTN Mobile Money',
                'currency' => 'XAF',
                'enabled' => true,
            ],
            'visa' => [
                'key' => 'visa',
                'name' => 'Visa',
                'shortName' => 'Visa',
                'icon' => 'fa-credit-card',
                'description' => 'Paiement par carte bancaire Visa',
                'currency' => 'XAF',
                'enabled' => true,
            ],
        ];
    }

    public function getMethodOrFail(string $method): array
    {
        $normalizedMethod = $this->normalizeMethod($method);
        $methods = $this->getAvailableMethods();
        $selected = $methods[$normalizedMethod] ?? null;

        if (!$selected) {
            throw new NotFoundHttpException('Méthode de paiement invalide.');
        }

        return $selected;
    }

    public function getCheckoutPageData(string $method): array
    {
        $selectedMethod = $this->getMethodOrFail($method);

        return [
            'methodKey' => $selectedMethod['key'],
            'method' => $selectedMethod,
            'methods' => array_values($this->getAvailableMethods()),
        ];
    }

    public function getApiCheckoutPayload(string $method): array
    {
        $selectedMethod = $this->getMethodOrFail($method);

        return [
            'ok' => true,
            'methodKey' => $selectedMethod['key'],
            'method' => $selectedMethod,
            'methods' => array_values($this->getAvailableMethods()),
        ];
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'om', 'orange-money', 'orange_money', 'orangemoney' => 'om',
            'momo', 'mobile-money', 'mobile_money', 'mtn-momo', 'mtn_momo', 'mtnmomo' => 'momo',
            'visa', 'card', 'cb', 'credit-card', 'credit_card' => 'visa',
            default => $method,
        };
    }
}
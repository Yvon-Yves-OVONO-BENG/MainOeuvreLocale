<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BoostPlanService
{
    public function getAllPlans(): array
    {
        return [
            'Premium' => [
                'key' => 'premium',
                'name' => 'Premium',
                'price' => 5000,
                'icon' => 'fa-rocket',
                'tag' => 'Recommandé',
                'features' => [
                    'Mise en avant',
                    'Plus de contacts',
                    'Badge Premium',
                ],
            ],
            'Gold' => [
                'key' => 'gold',
                'name' => 'Gold',
                'price' => 10000,
                'icon' => 'fa-trophy',
                'tag' => 'Boost',
                'features' => [
                    'Top résultats',
                    'Contacts ++',
                    'Badge Gold',
                ],
            ],
            'Vip' => [
                'key' => 'vip',
                'name' => 'VIP',
                'price' => 25000,
                'icon' => 'fa-crown',
                'tag' => 'Elite',
                'features' => [
                    'Priorité maximale',
                    'Contacts illimités',
                    'Support prioritaire',
                ],
            ],
        ];
    }

    public function getPlanOrFail(string $plan): array
    {
        
        $plans = $this->getAllPlans();
        
        $selected = $plans[$plan] ?? null;
        
        if (!$selected) {
            throw new NotFoundHttpException('Plan invalide.');
        }

        return $selected;
    }

    public function getPlansPageData(): array
    {
        return [
            'plans' => $this->getAllPlans(),
        ];
    }

    public function getCheckoutPageData(string $planKey): array
    {
        return [
            'planKey' => $planKey,
            'plan' => $this->getPlanOrFail($planKey),
        ];
    }

    public function getApiPlansPayload(): array
    {
        return [
            'ok' => true,
            'plans' => array_values($this->getAllPlans()),
        ];
    }

    public function getApiCheckoutPayload(string $planKey): array
    {
        return [
            'ok' => true,
            'planKey' => $planKey,
            'plan' => $this->getPlanOrFail($planKey),
        ];
    }
}
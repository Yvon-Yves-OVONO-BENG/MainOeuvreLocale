<?php

namespace App\Service;

use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PricingService
{
    public function __construct(
        private PlanRepository $planRepository,
        private SubscriptionRepository $subscriptionRepository,
        private TokenStorageInterface $tokenStorage
    ) {}

    public function getIndexData(): array
    {
        // Récupérer tous les plans actifs
        $plans = $this->planRepository->findBy(['isActive' => true]);

        // Récupérer l'utilisateur connecté
        $user = null;
        $currentPlan = null;
        $token = $this->tokenStorage->getToken();
        if ($token) {
            $user = $token->getUser();
            if ($user && !is_string($user)) {
                $currentPlan = $this->getCurrentPlan($user);
            }
        }

        // Définir les icônes, badges et couleurs par plan
        $planMeta = [
            'free' => [
                'icon' => 'fa-leaf',
                'badge' => 'Gratuit',
                'badgeIcon' => 'fa-gift',
                'color' => 'success',
                'btnClass' => 'btn-light',
                'btnText' => 'Commencer',
                'btnIcon' => 'fa-user-plus',
                'featured' => false,
                'btnAction' => 'app_register'
            ],
            'premium' => [
                'icon' => 'fa-rocket',
                'badge' => 'Recommandé',
                'badgeIcon' => 'fa-star',
                'color' => 'primary',
                'btnClass' => 'btn-primary',
                'btnText' => 'Passer en Premium',
                'btnIcon' => 'fa-bolt',
                'featured' => true,
                'btnAction' => 'subscribe_premium'
            ],
            'vip' => [
                'icon' => 'fa-crown',
                'badge' => 'Elite',
                'badgeIcon' => 'fa-crown',
                'color' => 'danger',
                'btnClass' => 'btn-dark',
                'btnText' => 'Devenir VIP',
                'btnIcon' => 'fa-crown',
                'featured' => false,
                'btnAction' => 'subscribe_vip'
            ]
        ];

        // Enrichir les plans avec les métadonnées
        $plansWithMeta = [];
        foreach ($plans as $plan) {
            $slug = $plan->getSlug();
            $meta = $planMeta[$slug] ?? [
                'icon' => 'fa-circle',
                'badge' => $plan->getName(),
                'badgeIcon' => 'fa-tag',
                'color' => 'secondary',
                'btnClass' => 'btn-outline-secondary',
                'btnText' => 'Choisir',
                'btnIcon' => 'fa-arrow-right',
                'featured' => false,
                'btnAction' => 'subscribe_plan'
            ];

            // Vérifier si l'utilisateur a déjà ce plan
            $isCurrent = $currentPlan && $currentPlan->getId() === $plan->getId();

            $plansWithMeta[] = [
                'entity' => $plan,
                'meta' => $meta,
                'isCurrent' => $isCurrent,
                'slug' => $slug,
                'features' => $plan->getFeatures() ?? [],
                'priceFormatted' => $plan->getPrice() > 0 ? number_format($plan->getPrice(), 0, ',', ' ') : '0',
                'priceLabel' => $plan->getPrice() > 0 ? 'XAF / mois' : 'Gratuit'
            ];
        }

        return [
            'plans' => $plansWithMeta,
            'currentPlan' => $currentPlan,
            'user' => $user
        ];
    }

    /**
     * Récupère le plan actif d'un utilisateur
     */
    private function getCurrentPlan($user)
    {
        $subscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'isActive' => true
        ], ['startAt' => 'DESC']);

        if (!$subscription) {
            return $this->planRepository->findOneBy(['slug' => 'free']);
        }

        if ($subscription->isExpired()) {
            $subscription->setIsActive(false);
            // On ne flush pas ici pour ne pas modifier la base en lecture
            return $this->planRepository->findOneBy(['slug' => 'free']);
        }

        return $subscription->getPlan();
    }
}
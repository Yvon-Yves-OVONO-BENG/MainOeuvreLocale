<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\PlanDuration;
use App\Entity\User;
use App\Entity\Subscription;
use App\Entity\StatusSubscription;
use App\Repository\PlanRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ContactLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlanManager
{
    private array $planMeta = [
        'decouverte' => [
            'icon' => 'fa-seedling',
            'color' => '#10b981',
            'bgColor' => 'rgba(16,185,129,0.10)',
            'badge' => 'Gratuit',
            'badgeIcon' => 'fa-gift',
            'btnClass' => 'btn-outline-success',
            'btnText' => 'Commencer',
            'btnIcon' => 'fa-user-plus',
            'featured' => false,
            'borderColor' => '#10b981'
        ],
        'pro' => [
            'icon' => 'fa-rocket',
            'color' => '#0ea5e9',
            'bgColor' => 'rgba(14,165,233,0.10)',
            'badge' => 'Populaire',
            'badgeIcon' => 'fa-fire',
            'btnClass' => 'btn-primary',
            'btnText' => 'Acheter un ticket',
            'btnIcon' => 'fa-arrow-right',
            'featured' => true,
            'borderColor' => '#0ea5e9'
        ],
        'premium' => [
            'icon' => 'fa-crown',
            'color' => '#f59e0b',
            'bgColor' => 'rgba(245,158,11,0.10)',
            'badge' => 'Premium',
            'badgeIcon' => 'fa-star',
            'btnClass' => 'btn-warning',
            'btnText' => 'Passer Premium',
            'btnIcon' => 'fa-bolt',
            'featured' => false,
            'borderColor' => '#f59e0b'
        ]
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private PlanRepository $planRepository,
        private SubscriptionRepository $subscriptionRepository,
        private ContactLogRepository $contactLogRepository
    ) {}

    public function getPlanMeta(Plan $plan): array
    {
        return $this->planMeta[$plan->getSlug()] ?? [
            'icon' => 'fa-circle',
            'color' => '#64748b',
            'bgColor' => 'rgba(100,116,139,0.10)',
            'badge' => $plan->getName(),
            'badgeIcon' => 'fa-tag',
            'btnClass' => 'btn-outline-secondary',
            'btnText' => 'Choisir',
            'btnIcon' => 'fa-arrow-right',
            'featured' => false,
            'borderColor' => '#64748b'
        ];
    }

    public function getActivePlans(): array
    {
        return $this->planRepository->findBy(['isActive' => true]);
    }

    public function getPlanBySlug(string $slug): ?Plan
    {
        return $this->planRepository->findOneBy(['slug' => $slug]);
    }

    public function getCurrentPlan(User $user): ?Plan
    {
        $subscription = $this->subscriptionRepository->findOneBy([
            'user' => $user,
            'isActive' => true
        ], ['startAt' => 'DESC']);

        if (!$subscription) {
            return $this->planRepository->findOneBy(['slug' => 'decouverte']);
        }

        if ($subscription->isExpired()) {
            $subscription->setIsActive(false);
            $this->em->flush();
            return $this->planRepository->findOneBy(['slug' => 'decouverte']);
        }

        return $subscription->getPlan();
    }

    public function canChat(User $user, User $targetUser): bool
    {
        $userPlan = $this->getCurrentPlan($user);
        $targetPlan = $this->getCurrentPlan($targetUser);

        if (!$userPlan || !$targetPlan) {
            return false;
        }

        if (!$userPlan->getCanChat()) {
            return false;
        }

        // Premium peut parler à tout le monde
        if ($userPlan->getSlug() === 'premium') {
            return true;
        }

        // Pro peut parler à Pro et Découverte
        if ($userPlan->getSlug() === 'pro') {
            $allowedSlugs = ['pro', 'decouverte'];
            return in_array($targetPlan->getSlug(), $allowedSlugs);
        }

        return false;
    }

    public function canVideoCall(User $user): bool
    {
        $plan = $this->getCurrentPlan($user);
        return $plan ? $plan->getCanVideoCall() : false;
    }

    /**
     * Récupère le nombre de contacts restants pour un utilisateur
     */
    public function getRemainingContacts(User $user): int
    {
        $plan = $this->getCurrentPlan($user);
        if (!$plan) {
            return 0;
        }

        $maxContacts = $plan->getMaxContacts();
        if ($maxContacts >= 99999) {
            return PHP_INT_MAX; // Illimité
        }

        $usedContacts = $this->countUsedContacts($user);
        $remaining = max(0, $maxContacts - $usedContacts);
        
        // Debug
        error_log('📊 getRemainingContacts - Plan: ' . $plan->getName() . ', Max: ' . $maxContacts . ', Used: ' . $usedContacts . ', Remaining: ' . $remaining);
        
        return $remaining;
    }

    /**
     * Compte le nombre de contacts utilisés par un utilisateur
     * ✅ Correction : Utilisation de QueryBuilder au lieu de count()
     */
    private function countUsedContacts(User $user): int
    {
        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        
        try {
            $count = $this->contactLogRepository->createQueryBuilder('cl')
                ->select('COUNT(cl.id)')
                ->where('cl.user = :user')
                ->andWhere('cl.createdAt >= :startOfMonth')
                ->setParameter('user', $user)
                ->setParameter('startOfMonth', $startOfMonth)
                ->getQuery()
                ->getSingleScalarResult();
            
            error_log('📊 countUsedContacts: ' . $count . ' pour le mois ' . $startOfMonth->format('Y-m-d'));
            
            return (int) $count;
        } catch (\Exception $e) {
            error_log('❌ Erreur countUsedContacts: ' . $e->getMessage());
            return 0;
        }
    }

    public function subscribe(
        User $user,
        Plan $plan,
        ?StatusSubscription $status = null,
        ?string $paymentId = null,
        ?string $paymentMethod = null,
        ?PlanDuration $duration = null
    ): Subscription {
        // Désactiver l'ancien abonnement
        $oldSubscriptions = $this->subscriptionRepository->findBy([
            'user' => $user,
            'isActive' => true
        ]);

        foreach ($oldSubscriptions as $old) {
            $old->setIsActive(false);
        }

        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setPlan($plan);
        $subscription->setIsActive(true);
        $subscription->setPaymentId($paymentId);
        $subscription->setPaymentMethod($paymentMethod);
        
        if ($status) {
            $subscription->setStatus($status);
        }

        if ($duration && (int) $duration->getMonths() > 0) {
            $endAt = (new \DateTime())->modify('+' . (int) $duration->getMonths() . ' months');
            $subscription->setEndAt($endAt);
        } elseif ($plan->getDurationDays() > 0) {
            $endAt = (new \DateTime())->modify('+' . $plan->getDurationDays() . ' days');
            $subscription->setEndAt($endAt);
        }

        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }
}

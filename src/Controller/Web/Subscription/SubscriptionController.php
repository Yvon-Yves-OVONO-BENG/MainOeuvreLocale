<?php

namespace App\Controller\Web\Subscription;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\StatusSubscription;
use App\Entity\User;
use App\Service\CurrencyConverter;
use App\Service\BoostStripeCardService;
use App\Service\PlanManager;
use App\Service\PaymentService;
use App\Repository\PlanDurationRepository;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SubscriptionController extends AbstractController
{
    // ✅ Page des plans : accessible à tous (même non connectés)
    #[Route('/abonnement/plans', name: 'subscription_plans')]
    public function plans(
        PlanDurationRepository $durationRepository,
        PlanRepository $planRepository,
        CurrencyConverter $currencyConverter
    ): Response {
        // Récupérer tous les plans actifs avec leurs durées
        $plans = $planRepository->findBy(['isActive' => true], ['price' => 'ASC']);
        
        // Vérifier si l'utilisateur a un abonnement actif
        $currentPlan = null;
        /**
         * @var User
         */
        $user = $this->getUser();
        
        if ($user) {
            $activeSubscription = $user->getSubscriptions()->filter(function($subscription) {
                return $subscription->isActiveSubscription();
            })->first();
            
            if ($activeSubscription) {
                $currentPlan = $activeSubscription->getPlan();
            }
        }
        
        // Récupérer les durées groupées par plan
        $groupedDurations = $durationRepository->findAllGroupedByPlan();
        
        return $this->render('subscription/plans.html.twig', [
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'grouped_durations' => $groupedDurations,
            'currency_converter' => $currencyConverter,
            'user_currency' => $currencyConverter->getUserCurrency(),
            'currency_symbol' => $currencyConverter->getCurrencySymbol(),
        ]);
    }

    // ✅ Choix du plan : accessible à tous (affiche les plans, mais la souscription nécessite connexion)
    #[Route('/abonnement/choisir/{slug}', name: 'subscription_choose')]
    public function choosePlan(
        string $slug, 
        Request $request,
        PlanManager $planManager,
        PlanDurationRepository $durationRepository,
        CurrencyConverter $currencyConverter
    ): Response {
        $plan = $planManager->getPlanBySlug($slug);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan non trouvé');
        }

        // Vérifier si c'est un plan gratuit
        if ($plan->getPrice() === 0) {
            // Rediriger vers l'inscription pour le plan découverte
            return $this->redirectToRoute('app_register', ['plan' => $slug]);
        }

        // Récupérer la durée sélectionnée
        $durationId = $request->query->get('duration');
        $selectedDuration = null;
        
        if ($durationId) {
            $selectedDuration = $durationRepository->find($durationId);
        }

        if ($selectedDuration && $selectedDuration->getPlan()?->getId() !== $plan->getId()) {
            $selectedDuration = null;
        }
        
        // Si pas de durée sélectionnée, prendre la première (1 mois)
        if (!$selectedDuration) {
            $durations = $durationRepository->findByPlan($plan);
            $selectedDuration = !empty($durations) ? $durations[0] : null;
        }

        if (!$selectedDuration) {
            throw $this->createNotFoundException('Aucune durée disponible pour ce plan');
        }

        // Convertir le prix
        $convertedPrice = $currencyConverter->convert($selectedDuration->getPrice());
        $formattedPrice = $currencyConverter->formatPrice($selectedDuration->getPrice());

        return $this->render('subscription/choose_payment.html.twig', [
            'plan' => $plan,
            'selected_duration' => $selectedDuration,
            'converted_price' => $convertedPrice,
            'formatted_price' => $formattedPrice,
            'user_currency' => $currencyConverter->getUserCurrency(),
            'currency_symbol' => $currencyConverter->getCurrencySymbol(),
        ]);
    }

    // ✅ Page de paiement : nécessite connexion
    #[Route('/abonnement/paiement/{slug}', name: 'subscription_payment')]
    #[IsGranted('ROLE_USER')]
    public function payment(
        string $slug, 
        Request $request, 
        PlanManager $planManager,
        PlanDurationRepository $durationRepository,
        CurrencyConverter $currencyConverter,
        BoostStripeCardService $stripeCardService
    ): Response {
        $plan = $planManager->getPlanBySlug($slug);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan non trouvé');
        }

        // Récupérer la durée sélectionnée
        $durationId = $request->query->get('duration');
        $selectedDuration = null;
        
        if ($durationId) {
            $selectedDuration = $durationRepository->find($durationId);
        }

        if ($selectedDuration && $selectedDuration->getPlan()?->getId() !== $plan->getId()) {
            $selectedDuration = null;
        }
        
        if (!$selectedDuration) {
            $this->addFlash('error', 'Veuillez sélectionner une durée d\'abonnement.');
            return $this->redirectToRoute('subscription_choose', ['slug' => $slug]);
        }

        $method = (string) $request->query->get('method', 'card');
        if (!in_array($method, ['card', 'orange', 'mtn'], true)) {
            $method = 'card';
        }

        if ($method !== 'card') {
            $this->addFlash('warning', 'Ce moyen de paiement sera disponible après connexion sécurisée à l’opérateur.');
            return $this->redirectToRoute('subscription_choose', [
                'slug' => $slug,
                'duration' => $selectedDuration->getId(),
            ]);
        }
        
        // Convertir le prix
        $convertedPrice = $currencyConverter->convert($selectedDuration->getPrice());
        $formattedPrice = $currencyConverter->formatPrice($selectedDuration->getPrice());

        return $this->render('subscription/payment.html.twig', [
            'plan' => $plan,
            'selected_duration' => $selectedDuration,
            'converted_price' => $convertedPrice,
            'formatted_price' => $formattedPrice,
            'method' => $method,
            'user_currency' => $currencyConverter->getUserCurrency(),
            'currency_symbol' => $currencyConverter->getCurrencySymbol(),
            'stripe_public_key' => $stripeCardService->getPublicKey(),
        ]);
    }

    // ✅ Traitement du paiement : nécessite connexion
    #[Route('/abonnement/process/{slug}', name: 'subscription_process', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function processSubscription(
        string $slug,
        Request $request,
        PlanManager $planManager,
        PlanDurationRepository $durationRepository,
        PaymentService $paymentService,
        EntityManagerInterface $em
    ): Response {
        $plan = $planManager->getPlanBySlug($slug);
        
        if (!$plan) {
            throw $this->createNotFoundException('Plan non trouvé');
        }

        // Récupérer la durée sélectionnée
        $durationId = $request->request->get('duration_id');
        $selectedDuration = null;
        
        if ($durationId) {
            $selectedDuration = $durationRepository->find($durationId);
        }

        if ($selectedDuration && $selectedDuration->getPlan()?->getId() !== $plan->getId()) {
            $selectedDuration = null;
        }
        
        if (!$selectedDuration) {
            $this->addFlash('error', 'La durée sélectionnée est invalide pour cette offre.');
            return $this->redirectToRoute('subscription_choose', ['slug' => $slug]);
        }

        if (!$this->isCsrfTokenValid(
            'subscription_process_' . $plan->getSlug(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('error', 'Votre session de paiement a expiré. Veuillez réessayer.');
            return $this->redirectToRoute('subscription_payment', [
                'slug' => $slug,
                'duration' => $selectedDuration->getId(),
            ]);
        }

        $user = $this->getUser();
        $paymentMethod = (string) $request->request->get('payment_method', 'card');
        if (!in_array($paymentMethod, ['card', 'orange', 'mtn'], true)) {
            $this->addFlash('error', 'Le moyen de paiement sélectionné est invalide.');
            return $this->redirectToRoute('subscription_choose', [
                'slug' => $slug,
                'duration' => $selectedDuration->getId(),
            ]);
        }
        $paymentToken = $request->request->get('payment_token');

        // Traiter le paiement avec le prix de la durée sélectionnée
        $paymentResult = $paymentService->processPayment([
            'amount' => $selectedDuration->getPrice(),
            'currency' => 'XAF',
            'method' => $paymentMethod,
            'token' => $paymentToken,
            'description' => 'Abonnement ' . $plan->getName() . ' - ' . $selectedDuration->getLabel(),
            'user' => $user,
            'plan' => $plan,
            'duration' => $selectedDuration,
        ]);

        if (!$paymentResult['success']) {
            $this->addFlash('error', $paymentResult['message'] ?? 'Le paiement a échoué. Veuillez réessayer.');
            return $this->redirectToRoute('subscription_payment', [
                'slug' => $slug,
                'duration' => $selectedDuration->getId(),
                'method' => $paymentMethod,
            ]);
        }

        $existingSubscription = $em->getRepository(Subscription::class)->findOneBy([
            'paymentId' => $paymentResult['transaction_id'],
        ]);
        if ($existingSubscription) {
            if ($existingSubscription->getUser()?->getId() !== $user?->getId()) {
                throw $this->createAccessDeniedException('Cette référence de paiement est déjà utilisée.');
            }

            $this->addFlash('success', 'Ce paiement a déjà été confirmé. Votre abonnement est actif.');
            return $this->redirectToRoute('tableau_de_bord');
        }

        // Créer l'abonnement avec la durée sélectionnée
        $status = $em->getRepository(StatusSubscription::class)->findOneBy(['slug' => 'active']);
        
        $subscription = $planManager->subscribe(
            $user,
            $plan,
            $status,
            $paymentResult['transaction_id'],
            $paymentMethod,
            $selectedDuration // Passer la durée sélectionnée
        );

        $this->addFlash('success', sprintf(
            'Votre abonnement %s a été activé avec succès pour %s !',
            $plan->getName(),
            $selectedDuration->getLabel()
        ));

        return $this->redirectToRoute('tableau_de_bord');
    }

    // ✅ Page de succès : accessible à tous (mais normalement après paiement)
    #[Route('/abonnement/success', name: 'subscription_success')]
    public function success(): Response
    {
        return $this->render('subscription/success.html.twig');
    }

    // ✅ Page d'annulation : accessible à tous
    #[Route('/abonnement/cancel', name: 'subscription_cancel')]
    public function cancel(): Response
    {
        return $this->render('subscription/cancel.html.twig');
    }
    
    // ✅ Index : accessible à tous
    #[Route('/abonnement', name: 'subscription_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('subscription_plans');
    }
}

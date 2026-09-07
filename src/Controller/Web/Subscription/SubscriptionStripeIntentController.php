<?php

namespace App\Controller\Web\Subscription;

use App\Entity\User;
use App\Repository\PlanDurationRepository;
use App\Repository\CategorieRepository;
use App\Repository\ProfessionRepository;
use App\Service\PlanManager;
use App\Service\SubscriptionStripePaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SubscriptionStripeIntentController extends AbstractController
{
    #[Route('/abonnement/stripe/intent/{slug}', name: 'subscription_stripe_intent', methods: ['POST'])]
    public function __invoke(
        string $slug,
        Request $request,
        PlanManager $planManager,
        PlanDurationRepository $durationRepository,
        CategorieRepository $categorieRepository,
        ProfessionRepository $professionRepository,
        SubscriptionStripePaymentService $stripePaymentService
    ): JsonResponse {
        $plan = $planManager->getPlanBySlug($slug);
        if (!$plan) {
            return $this->json(['ok' => false, 'message' => 'Offre introuvable.'], 404);
        }

        if (!$this->isCsrfTokenValid(
            'subscription_stripe_intent_' . $plan->getSlug(),
            (string) $request->headers->get('X-CSRF-Token')
        )) {
            return $this->json(['ok' => false, 'message' => 'Session de paiement expirée.'], 419);
        }

        $payload = json_decode($request->getContent(), true);
        $durationId = is_array($payload) ? (int) ($payload['duration_id'] ?? 0) : 0;
        $categorieId = is_array($payload) ? (int) ($payload['categorie_id'] ?? 0) : 0;
        $professionId = is_array($payload) ? (int) ($payload['profession_id'] ?? 0) : 0;
        $duration = $durationId > 0 ? $durationRepository->find($durationId) : null;

        if (!$duration || $duration->getPlan()?->getId() !== $plan->getId()) {
            return $this->json(['ok' => false, 'message' => 'Durée d’abonnement invalide.'], 400);
        }

        $categorie = $plan->getSlug() === 'pro' ? $categorieRepository->find($categorieId) : null;
        $profession = $plan->getSlug() === 'pro' ? $professionRepository->find($professionId) : null;
        if ($plan->getSlug() === 'pro' && (
            !$categorie
            || !$categorie->isIsActive()
            || !$profession
            || $profession->getCategorie()?->getId() !== $categorie->getId()
        )) {
            return $this->json(['ok' => false, 'message' => 'Catégorie du ticket invalide.'], 400);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Connexion requise.'], 401);
        }

        try {
            return $this->json($stripePaymentService->createIntent($plan, $duration, $user, $profession));
        } catch (BadRequestHttpException $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], 400);
        }
    }
}

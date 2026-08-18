<?php

namespace App\Controller\Web\Boost;

use App\Entity\User;
use App\Service\BoostStripeIntentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostStripeIntentController extends AbstractController
{
    #[Route('/boost-stripe-intent', name: 'boost_stripe_intent', methods: ['POST'])]
    public function __invoke(
        Request $request,
        BoostStripeIntentService $boostStripeIntentService
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        $plan = $payload['plan'] ?? $request->request->get('plan');
        $montant = $payload['montant'] ?? $request->request->get('montant');

        if ($plan === null || $plan === '') {
            return $this->json([
                'ok' => false,
                'message' => "Le champ 'plan' est requis.",
            ], 400);
        }

        if ($montant === null || $montant === '') {
            return $this->json([
                'ok' => false,
                'message' => "Le champ 'montant' est requis.",
            ], 400);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $user = $user instanceof User ? $user : null;

        try {
            return $this->json(
                $boostStripeIntentService->createIntent((string) $plan, (int) $montant, $user)
            );
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        } catch (BadRequestHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }
}
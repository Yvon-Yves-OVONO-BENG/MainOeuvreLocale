<?php

namespace App\Controller\Web\Boost;

use App\Service\BoostStripeCardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostStripeCardController extends AbstractController
{
    #[Route('/boost-card/{plan}/{montant}', name: 'boost_stripe_card', methods: ['GET'])]
    public function __invoke(
        string $plan,
        int $montant,
        BoostStripeCardService $boostStripeCardService
    ): Response {
        try {
            return $this->render(
                'boost_card/boost_card.html.twig',
                $boostStripeCardService->getCheckoutPageData($plan, $montant)
            );
        } catch (NotFoundHttpException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        } catch (BadRequestHttpException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('payment_boost', [
                'plan' => $plan,
            ]);
        }
    }
}
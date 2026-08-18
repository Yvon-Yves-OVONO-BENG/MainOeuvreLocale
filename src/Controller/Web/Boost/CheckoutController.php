<?php

namespace App\Controller\Web\Boost;

use App\Service\BoostPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    #[Route('/payment-boost/{plan}', name: 'payment_boost', requirements: ['plan' => 'premium|gold|vip'], methods: ['GET'])]
    public function __invoke(string $plan, BoostPlanService $boostPlanService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TALENT');

        try {
            return $this->render(
                'payment/boost_checkout.html.twig',
                $boostPlanService->getCheckoutPageData($plan)
            );
        } catch (NotFoundHttpException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }
    }
}
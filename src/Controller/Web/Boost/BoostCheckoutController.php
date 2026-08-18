<?php

namespace App\Controller\Web\Boost;

use App\Service\BoostCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostCheckoutController extends AbstractController
{
    #[Route('/boost-checkout/{method}', name: 'boost_checkout', methods: ['GET'])]
    public function __invoke(string $method, BoostCheckoutService $boostCheckoutService): Response
    {
        try {
            return $this->render(
                'boost_checkout/boost_checkout.html.twig',
                $boostCheckoutService->getCheckoutPageData($method)
            );
        } catch (NotFoundHttpException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }
    }
}
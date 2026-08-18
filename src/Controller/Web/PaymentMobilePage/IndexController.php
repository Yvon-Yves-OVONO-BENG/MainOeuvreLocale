<?php

namespace App\Controller\Web\PaymentMobilePage;

use App\Service\PaymentMobilePageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class IndexController extends AbstractController
{
    #[Route('/payment-boost/{plan}/{montant}/mobile/{method}', name: 'payment_boost_mobile_page', methods: ['GET'])]
    public function __invoke(
        string $plan,
        int $montant,
        string $method,
        PaymentMobilePageService $paymentMobilePageService
    ): Response {
        
        try {
            return $this->render(
                'payment_boost/mobile_pay.html.twig',
                $paymentMobilePageService->getPageData($plan, $montant, $method)
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
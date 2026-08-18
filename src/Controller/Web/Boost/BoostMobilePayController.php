<?php

namespace App\Controller\Web\Boost;

use App\Service\BoostMobilePayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
final class BoostMobilePayController extends AbstractController
{
    #[Route('/boost-mobile-pay/{plan}/{montant}', name: 'boost_mobile_pay', methods: ['POST'])]
    public function __invoke(
        Request $request,
        string $plan,
        int $montant,
        BoostMobilePayService $boostMobilePayService
    ): Response {
        if (!$this->isCsrfTokenValid('boost_mobile_pay', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $method = (string) $request->request->get('method');
        $phone = (string) $request->request->get('phone');

        try {
            $result = $boostMobilePayService->process($plan, $montant, $method, $phone);

            $this->addFlash('success', $result['message']);
        } catch (NotFoundHttpException|BadRequestHttpException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        return $this->redirectToRoute('payment_boost', [
            'plan' => $plan,
        ]);
    }
}
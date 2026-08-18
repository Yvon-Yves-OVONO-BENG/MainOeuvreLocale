<?php

namespace App\Controller\Web\TalentPaymentDispute;

use App\Entity\User;
use App\Form\PaymentDisputeClientType;
use App\Service\TalentPaymentDisputeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/talent')]
final class CreateController extends AbstractController
{
    #[Route('/payment-disputes/new', name: 'talent_payment_dispute_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        TalentPaymentDisputeService $talentPaymentDisputeService
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $paymentDispute = $talentPaymentDisputeService->createDisputeDraft($user);

        $form = $this->createForm(PaymentDisputeClientType::class, $paymentDispute, [
            'user' => $user,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $talentPaymentDisputeService->submitNewDispute($paymentDispute, $user);

                $this->addFlash('success', $result['message']);

                return $this->redirectToRoute('talent_payment_dispute_new');
            } catch (ConflictHttpException $exception) {
                $this->addFlash('warning', $exception->getMessage());

                return $this->redirectToRoute('talent_payment_dispute_new');
            } catch (BadRequestHttpException $exception) {
                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('talent_payment_dispute_new');
            }
        }

        return $this->render('talent/payment_dispute/payment_dispute_new.html.twig', array_merge(
            $talentPaymentDisputeService->getNewPageData($user),
            [
                'form' => $form->createView(),
            ]
        ));
    }
}
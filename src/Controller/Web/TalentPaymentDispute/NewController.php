<?php

namespace App\Controller\Web\TalentPaymentDispute;

use App\Entity\User;
use App\Form\PaymentDisputeClientType;
use App\Service\TalentPaymentDisputeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/talent')]
final class NewController extends AbstractController
{
    #[Route('/payment-disputes/new', name: 'talent_payment_dispute_new', methods: ['GET'])]
    public function __invoke(TalentPaymentDisputeService $talentPaymentDisputeService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $paymentDispute = $talentPaymentDisputeService->createDisputeDraft($user);

        $form = $this->createForm(PaymentDisputeClientType::class, $paymentDispute, [
            'user' => $user,
        ]);

        return $this->render('talent/payment_dispute/payment_dispute_new.html.twig', array_merge(
            $talentPaymentDisputeService->getNewPageData($user),
            [
                'form' => $form->createView(),
            ]
        ));
    }
}
<?php

namespace App\Controller\Web\TalentPaymentDispute;

use App\Entity\User;
use App\Service\TalentPaymentDisputeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/talent')]
final class IndexController extends AbstractController
{
    #[Route('/payment-disputes', name: 'talent_payment_dispute_index', methods: ['GET'])]
    public function __invoke(TalentPaymentDisputeService $talentPaymentDisputeService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render(
            'talent/payment_dispute/payment_dispute.html.twig',
            $talentPaymentDisputeService->getIndexData($user)
        );
    }
}
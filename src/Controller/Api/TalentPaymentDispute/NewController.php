<?php

namespace App\Controller\Api\TalentPaymentDispute;

use App\Entity\User;
use App\Service\TalentPaymentDisputeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/talent')]
final class NewController extends AbstractController
{
    #[Route('/payment-disputes/new', name: 'api_talent_payment_dispute_new', methods: ['GET'])]
    public function __invoke(TalentPaymentDisputeService $talentPaymentDisputeService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'ok' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        return $this->json(
            $talentPaymentDisputeService->getApiNewPayload($user)
        );
    }
}
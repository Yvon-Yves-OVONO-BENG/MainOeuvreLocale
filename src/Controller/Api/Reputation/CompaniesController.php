<?php

namespace App\Controller\Api\Reputation;

use App\Entity\User;
use App\Service\ReputationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CompaniesController extends AbstractController
{
    #[Route('/api/reputation/companies', name: 'api_reputation_companies', methods: ['GET'])]
    public function __invoke(Request $request, ReputationService $reputationService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $viewer = $this->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        $q = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        return $this->json(
            $reputationService->getApiListingPayload(
                'ROLE_COMPANY',
                'Companies',
                'reputation_companies',
                $viewer,
                $q,
                $page
            )
        );
    }
}
<?php

namespace App\Controller\Api\TalentSanction;

use App\Entity\User;
use App\Service\TalentSanctionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/talent')]
final class IndexController extends AbstractController
{
    #[Route('/sanctions', name: 'api_talent_sanctions_index', methods: ['GET'])]
    public function __invoke(TalentSanctionService $talentSanctionService): JsonResponse
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
            $talentSanctionService->getApiIndexPayload($user)
        );
    }
}
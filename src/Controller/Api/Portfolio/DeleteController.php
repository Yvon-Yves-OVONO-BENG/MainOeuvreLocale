<?php

namespace App\Controller\Api\Portfolio;

use App\Entity\ProfessionalMedia;
use App\Entity\User;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DeleteController extends AbstractController
{
    #[Route('/api/talent-portfolio/{id}', name: 'api_talent_portfolio_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_TALENT')]
    public function __invoke(
        ProfessionalMedia $media,
        PortfolioService $portfolioService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        $result = $portfolioService->getApiDeletePayload($user, $media);

        return $this->json($result, $result['ok'] ? 200 : 400);
    }
}
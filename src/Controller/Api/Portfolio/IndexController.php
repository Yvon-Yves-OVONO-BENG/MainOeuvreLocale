<?php

namespace App\Controller\Api\Portfolio;

use App\Entity\User;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IndexController extends AbstractController
{
    #[Route('/api/talent-portfolio', name: 'api_talent_portfolio', methods: ['GET'])]
    #[IsGranted('ROLE_TALENT')]
    public function __invoke(PortfolioService $portfolioService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $this->json(
            $portfolioService->getApiIndexPayload($user)
        );
    }
}
<?php

namespace App\Controller\Api\Portfolio;

use App\Entity\User;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UploadController extends AbstractController
{
    #[Route('/api/talent-portfolio', name: 'api_talent_portfolio_upload', methods: ['POST'])]
    #[IsGranted('ROLE_TALENT')]
    public function __invoke(Request $request, PortfolioService $portfolioService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $files = $request->files->all('files');

        $result = $portfolioService->uploadFiles($user, is_array($files) ? $files : []);

        return $this->json($result, $result['ok'] ? 201 : 400);
    }
}
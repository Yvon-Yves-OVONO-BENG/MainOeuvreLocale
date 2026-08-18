<?php

namespace App\Controller\Web\Portfolio;

use App\Entity\ProfessionalMedia;
use App\Entity\User;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DeleteController extends AbstractController
{
    #[Route('/talent-portfolio/{id}/delete', name: 'talent_portfolio_delete', methods: ['POST'])]
    #[IsGranted('ROLE_TALENT')]
    public function __invoke(
        ProfessionalMedia $media,
        Request $request,
        PortfolioService $portfolioService
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        $result = $portfolioService->deleteMedia(
            $user,
            $media,
            (string) $request->request->get('_token'),
            true
        );

        $this->addFlash($result['ok'] ? 'success' : 'danger', $result['message']);

        return $this->redirectToRoute('talent_portfolio');
    }
}
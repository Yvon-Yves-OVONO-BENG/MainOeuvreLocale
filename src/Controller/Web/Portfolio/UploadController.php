<?php

namespace App\Controller\Web\Portfolio;

use App\Entity\User;
use App\Form\PortfolioUploadType;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UploadController extends AbstractController
{
    #[Route('/talent-portfolio', name: 'talent_portfolio_upload', methods: ['POST'])]
    #[IsGranted('ROLE_TALENT')]
    public function __invoke(Request $request, PortfolioService $portfolioService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $form = $this->createForm(PortfolioUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData() ?? [];
            $result = $portfolioService->uploadFiles($user, $files);

            $this->addFlash($result['ok'] ? 'success' : 'danger', $result['message']);

            return $this->redirectToRoute('talent_portfolio');
        }

        return $this->render('portfolio/portfolio.html.twig', array_merge(
            $portfolioService->getPortfolioPageData($user),
            [
                'form' => $form->createView(),
            ]
        ));
    }
}
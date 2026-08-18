<?php

namespace App\Controller\Web\Portfolio;

use App\Entity\User;
use App\Form\PortfolioUploadType;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IndexController extends AbstractController
{
    #[Route('/talent-portfolio', name: 'talent_portfolio', methods: ['GET'])]
    #[IsGranted('ROLE_TALENT')]
    public function __invoke(PortfolioService $portfolioService): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $form = $this->createForm(PortfolioUploadType::class);

        return $this->render('portfolio/portfolio.html.twig', array_merge(
            $portfolioService->getPortfolioPageData($user),
            [
                'form' => $form->createView(),
            ]
        ));
    }
}
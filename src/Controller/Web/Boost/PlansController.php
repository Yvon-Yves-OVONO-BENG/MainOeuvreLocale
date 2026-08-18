<?php

namespace App\Controller\Web\Boost;

use App\Service\BoostPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlansController extends AbstractController
{
    #[Route('/boost-plan', name: 'boost_plans', methods: ['GET'])]
    public function __invoke(BoostPlanService $boostPlanService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', 'Accès refusé. Connectez-vous');

        return $this->render(
            'boost_plan/boost_plan.html.twig',
            $boostPlanService->getPlansPageData()
        );
    }
}
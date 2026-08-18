<?php

namespace App\Controller\Web\Planning;

use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
final class IndexController extends AbstractController
{
    #[Route('/planning', name: 'planning', methods: ['GET'])]
    public function __invoke(PlanningService $planningService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $planningService->requireUser($this->getUser());

        return $this->render(
            'planning/planning.html.twig',
            $planningService->getPlanningData($me)
        );
    }
}
<?php

namespace App\Controller\Web\BriefTemplate;

use App\Service\BriefTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/particulier/briefs', name: 'brief_templates_')]
final class UseController extends AbstractController
{
    #[Route('/{slug}/use', name: 'use', methods: ['GET'])]
    public function __invoke(string $slug, BriefTemplateService $briefTemplateService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            $data = $briefTemplateService->getUseTemplateData($slug);

            return $this->redirectToRoute(
                $data['routeName'],
                $data['routeParams']
            );
        } catch (NotFoundHttpException $exception) {
            throw $this->createNotFoundException($exception->getMessage());
        }
    }
}
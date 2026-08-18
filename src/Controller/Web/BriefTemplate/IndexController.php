<?php

namespace App\Controller\Web\BriefTemplate;

use App\Service\BriefTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/particulier/briefs', name: 'brief_templates_')]
final class IndexController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function __invoke(BriefTemplateService $briefTemplateService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render(
            'briefs/briefs.html.twig',
            $briefTemplateService->getIndexData()
        );
    }
}
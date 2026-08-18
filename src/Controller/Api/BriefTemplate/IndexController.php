<?php

namespace App\Controller\Api\BriefTemplate;

use App\Service\BriefTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/particulier/briefs', name: 'api_brief_templates_')]
final class IndexController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function __invoke(BriefTemplateService $briefTemplateService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json(
            $briefTemplateService->getApiIndexPayload()
        );
    }
}
<?php

namespace App\Controller\Api\BriefTemplate;

use App\Service\BriefTemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/particulier/briefs', name: 'api_brief_templates_')]
final class UseController extends AbstractController
{
    #[Route('/{slug}/use', name: 'use', methods: ['GET'])]
    public function __invoke(string $slug, BriefTemplateService $briefTemplateService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            return $this->json(
                $briefTemplateService->getApiUseTemplatePayload($slug)
            );
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }
}
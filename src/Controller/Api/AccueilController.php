<?php

namespace App\Controller\Api;

use App\Service\Accueil\AccueilService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/api/accueil', name: 'api_accueil', methods: ['GET'])]
    public function index(AccueilService $accueilService): JsonResponse
    {
        $data = $accueilService->getAccueilData();

        return $this->json([
            'success' => true,
            'data' => $this->normalizeAccueilData($data),
        ]);
    }

    private function normalizeAccueilData(array $data): array
    {
        return [
            'types' => $data['types'] ?? [],
            'jobsByType' => $data['jobsByType'] ?? [],
            'categoriesWithCounts' => $data['categoriesWithCounts'] ?? [],
        ];
    }
}
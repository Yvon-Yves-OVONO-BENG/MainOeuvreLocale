<?php

namespace App\Controller\Api\Profession;

use App\Service\ProfessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ByCategorieController extends AbstractController
{
    #[Route('/api/professions/{categorieId}', name: 'api_professions_by_categorie', methods: ['GET'])]
    public function __invoke(int $categorieId, ProfessionService $professionService): JsonResponse
    {
        return $this->json(
            $professionService->getApiByCategoriePayload($categorieId)
        );
    }
}
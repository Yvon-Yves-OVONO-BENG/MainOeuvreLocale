<?php

namespace App\Controller\Web\Search;

use App\Service\Search\SearchInputSanitizer;
use App\Service\Search\SearchSuggestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchSuggestionController extends AbstractController
{
    #[Route('/api/recherche/suggestions', name: 'api_search_suggestions', methods: ['GET'])]
    public function __invoke(
        Request $request,
        SearchInputSanitizer $sanitizer,
        SearchSuggestionService $suggestions,
    ): JsonResponse {
        $type = strtolower(trim((string) $request->query->get('type', 'keyword')));
        $isLocation = $type === 'location';

        try {
            $query = $isLocation
                ? $sanitizer->sanitizeLocation($request->query->get('q', ''), 120)
                : $sanitizer->sanitizeKeyword($request->query->get('q', ''), 80);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['ok' => false, 'message' => $exception->getMessage(), 'items' => []],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->json([
            'ok' => true,
            'items' => $isLocation
                ? $suggestions->suggestLocations($query, $request->query->getInt('limit', 10))
                : $suggestions->suggest($query, $request->query->getInt('limit', 10)),
        ]);
    }
}

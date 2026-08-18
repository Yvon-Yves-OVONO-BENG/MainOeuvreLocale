<?php

namespace App\Controller\Web\Job;

use App\Service\JobGeolocationService;
use App\Service\Search\SearchInputSanitizer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JobNearbyMapController extends AbstractController
{
    #[Route('/api/jobs/proximite', name: 'api_jobs_nearby_map', methods: ['POST'])]
    public function __invoke(
        Request $request,
        JobGeolocationService $geolocation,
        SearchInputSanitizer $searchInputSanitizer,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('nearby_job_map', (string) ($payload['_token'] ?? ''))) {
            return $this->json(
                ['ok' => false, 'message' => 'La session de recherche a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $keyword = $searchInputSanitizer->sanitizeKeyword($payload['q'] ?? '', 100);
            $city = $searchInputSanitizer->sanitizeLocation($payload['city'] ?? '', 100);
            $jobs = $geolocation->searchNearby(
                $payload['latitude'] ?? null,
                $payload['longitude'] ?? null,
                $payload['radius'] ?? null,
                $keyword,
                $city,
            );

            foreach ($jobs as &$job) {
                $job['url'] = $this->generateUrl('job_detail', ['slug' => $job['slug']]);
                unset($job['id'], $job['slug']);
            }
            unset($job);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['ok' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $exception) {
            $logger->error('Erreur pendant la recherche cartographique des offres.', [
                'exception' => $exception,
            ]);

            return $this->json(
                ['ok' => false, 'message' => 'La carte est momentanément indisponible. Veuillez réessayer.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json([
            'ok' => true,
            'count' => count($jobs),
            'jobs' => $jobs,
        ]);
    }
}

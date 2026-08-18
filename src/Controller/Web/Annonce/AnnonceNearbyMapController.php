<?php

namespace App\Controller\Web\Annonce;

use App\Service\AnnonceGeolocationService;
use App\Service\Search\SearchInputSanitizer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnonceNearbyMapController extends AbstractController
{
    #[Route('/api/annonces/proximite', name: 'api_annonces_nearby_map', methods: ['POST'])]
    public function __invoke(
        Request $request,
        AnnonceGeolocationService $geolocation,
        SearchInputSanitizer $searchInputSanitizer,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('nearby_annonce_map', (string) ($payload['_token'] ?? ''))) {
            return $this->json(
                ['ok' => false, 'message' => 'La session de recherche a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $keyword = $searchInputSanitizer->sanitizeKeyword($payload['q'] ?? '', 100);
            $city = $searchInputSanitizer->sanitizeLocation($payload['city'] ?? '', 100);
            $annonces = $geolocation->searchNearby(
                $payload['latitude'] ?? null,
                $payload['longitude'] ?? null,
                $payload['radius'] ?? null,
                $keyword,
                $city,
            );

            foreach ($annonces as &$annonce) {
                $annonce['url'] = $this->generateUrl('annonce_show', ['slug' => $annonce['slug']]);
                unset($annonce['id'], $annonce['slug']);
            }
            unset($annonce);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['ok' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $exception) {
            // N'expose jamais un warning PHP/HTML dans une réponse JSON.
            $logger->error('Erreur pendant la recherche cartographique des annonces.', [
                'exception' => $exception,
            ]);

            return $this->json(
                ['ok' => false, 'message' => 'La carte est momentanément indisponible. Veuillez réessayer.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json([
            'ok' => true,
            'count' => count($annonces),
            'annonces' => $annonces,
        ]);
    }
}

<?php

namespace App\Controller\Web\Talent;

use App\Service\TalentGeolocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NearbyTalentMapController extends AbstractController
{
    #[Route('/api/talents/proximite', name: 'api_talents_nearby_map', methods: ['POST'])]
    public function search(
        Request $request,
        TalentGeolocationService $geolocation,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(
                ['ok' => false, 'message' => 'Requête JSON invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->isCsrfTokenValid('nearby_talent_map', (string) ($payload['_token'] ?? ''))) {
            return $this->json(
                ['ok' => false, 'message' => 'La session de recherche a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $radius = filter_var(
            $payload['radius'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 100]]
        );

        if ($radius === false) {
            return $this->json(
                ['ok' => false, 'message' => 'Le rayon doit être un entier compris entre 0 et 100 km.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $q = mb_substr(trim((string) ($payload['q'] ?? '')), 0, 100);
        $city = mb_substr(trim((string) ($payload['city'] ?? '')), 0, 100);

        try {
            $talents = $geolocation->searchNearby(
                $payload['latitude'] ?? null,
                $payload['longitude'] ?? null,
                $radius,
                null,
                $q,
                $city,
                100,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['ok' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // L'URL est generee cote Symfony pour respecter automatiquement le
        // sous-dossier eventuel de l'installation et la configuration des routes.
        foreach ($talents as &$talent) {
            $slug = (string) ($talent['slug'] ?? '');
            $talent['profileUrl'] = $slug !== ''
                ? $this->generateUrl('profil_talent', ['slug' => $slug])
                : null;
            unset($talent['slug']);
        }
        unset($talent);

        return $this->json([
            'ok' => true,
            'count' => count($talents),
            'radius' => $radius,
            // Aucun téléphone ou e-mail n'est exposé ici.
            // Le popup utilise ensuite la route contact_show déjà protégée.
            'talents' => $talents,
        ]);
    }
}

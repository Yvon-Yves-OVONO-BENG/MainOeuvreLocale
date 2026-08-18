<?php

namespace App\Controller\Web\Talent;

use App\Entity\Profession;
use App\Entity\User;
use App\Service\TalentGeolocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/talents/proximite')]
final class NearbyTalentController extends AbstractController
{
    #[Route('', name: 'talents_nearby', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TalentGeolocationService $geolocation,
    ): Response {
        $latitude = null;
        $longitude = null;
        $radius = '0';
        $professionId = null;
        $searchError = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('nearby_talent_search', (string) $request->request->get('_token'))) {
                $searchError = 'La session de recherche a expiré. Rechargez la page puis réessayez.';
            } else {
                $latitude = $request->request->get('latitude');
                $longitude = $request->request->get('longitude');
                $radius = $request->request->get('radius', '0');
                $professionId = $request->request->getInt('profession') ?: null;
            }
        }

        $talents = [];
        $searchPerformed = $latitude !== null && $longitude !== null;

        if ($searchPerformed && $searchError === null) {
            try {
                $talents = $geolocation->searchNearby(
                    $latitude,
                    $longitude,
                    $radius,
                    $professionId,
                );
            } catch (\InvalidArgumentException $exception) {
                $searchError = $exception->getMessage();
            }
        }

        return $this->render('talent/nearby.html.twig', [
            'professions' => $em->getRepository(Profession::class)->findBy([], ['profession' => 'ASC']),
            'talents' => $talents,
            'searchPerformed' => $searchPerformed,
            'searchError' => $searchError,
            'filters' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius' => (float) $radius,
                'professionId' => $professionId,
            ],
        ]);
    }

    #[Route('/ma-position', name: 'talent_geolocation_update', methods: ['POST'])]
    #[IsGranted('ROLE_TALENT')]
    public function updateMyLocation(
        Request $request,
        EntityManagerInterface $em,
        TalentGeolocationService $geolocation,
    ): JsonResponse {
        if (!$request->isXmlHttpRequest() && $request->getContentTypeFormat() !== 'json') {
            return $this->json(['ok' => false, 'message' => 'Requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('talent_geolocation_update', (string) ($payload['_token'] ?? ''))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $profile = $user?->getProfessionalProfile();

        if (!$profile) {
            return $this->json(['ok' => false, 'message' => 'Profil professionnel introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $geolocation->applyConsent(
                $profile,
                (bool) ($payload['enabled'] ?? true),
                $payload['latitude'] ?? null,
                $payload['longitude'] ?? null,
            );
            $em->flush();
        } catch (\InvalidArgumentException $exception) {
            return $this->json(
                ['ok' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->json([
            'ok' => true,
            'enabled' => $profile->isGeolocationEnabled(),
            'updatedAt' => $profile->getLocationUpdatedAt()?->format(DATE_ATOM),
        ]);
    }
}

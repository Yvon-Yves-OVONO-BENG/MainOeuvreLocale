<?php

namespace App\Controller\Web\Talent;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\StatusProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TalentAvailabilityToggleController extends AbstractController
{
    #[Route('/talent-availability-toggle', name: 'talent_availability_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_TALENT')]
    public function toggleAvailability(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Non autorisé.'], 401);
        }

        $pro = $user->getProfessionalProfile();
        if (!$pro) {
            return $this->json(['ok' => false, 'message' => 'Profil professionnel introuvable.'], 404);
        }

        $data = json_decode($request->getContent() ?: '[]', true);

        // ✅ CSRF
        if (!$this->isCsrfTokenValid('toggle_availability', $data['_token'] ?? '')) {
            return $this->json(['ok' => false, 'message' => 'Token CSRF invalide.'], 419);
        }

        $desired = $data['desired'] ?? null; // 'available'|'unavailable'
        if (!in_array($desired, ['available', 'unavailable'], true)) {
            return $this->json(['ok' => false, 'message' => 'Demande invalide.'], 400);
        }

        // ✅ Récupérer les StatusProfile correspondants
        $wantedLabel = $desired === 'available' ? 'Disponible' : 'Indisponible';

        $status = $em->getRepository(StatusProfile::class)->createQueryBuilder('s')
            ->andWhere('LOWER(s.status) = :st')
            ->setParameter('st', mb_strtolower($wantedLabel))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$status) {
            return $this->json([
                'ok' => false,
                'message' => 'Le statut "' . $wantedLabel . '" n’existe pas en base (StatusProfile).'
            ], 500);
        }

        $pro->setStatusProfile($status);
        $em->flush();

        $badgeClass = $desired === 'available' ? 'text-bg-success' : 'text-bg-danger';

        return $this->json([
            'ok' => true,
            'statusLabel' => $wantedLabel,
            'badgeClass' => $badgeClass,
            'message' => $desired === 'available'
                ? 'Vous êtes maintenant disponible.'
                : 'Vous êtes maintenant indisponible.'
        ]);

    }
}

<?php

namespace App\Controller\Web\Talent;

use App\Entity\StatusProfile;
use App\Entity\TalentAvailability;
use App\Repository\TalentAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TalentAvailabilityCalendarController extends AbstractController
{
    #[Route('/talent-availability-calendar/save', name: 'talent_availability_calendar_save', methods: ['POST'])]
    #[IsGranted('ROLE_TALENT')]
    public function save(
        Request $request,
        EntityManagerInterface $em,
        TalentAvailabilityRepository $availabilityRepository
    ): JsonResponse {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Non autorisé.'], 401);
        }

        $pro = $user->getProfessionalProfile();

        if (!$pro) {
            return $this->json(['ok' => false, 'message' => 'Profil professionnel introuvable.'], 404);
        }

        $data = json_decode($request->getContent() ?: '[]', true);

        if (!$this->isCsrfTokenValid('save_availability_calendar', $data['_token'] ?? '')) {
            return $this->json(['ok' => false, 'message' => 'Token CSRF invalide.'], 419);
        }

        $dates = $data['dates'] ?? [];

        if (!is_array($dates)) {
            return $this->json(['ok' => false, 'message' => 'Format des dates invalide.'], 400);
        }

        // Dates reçues et validées
        $newDates = [];
        foreach ($dates as $date) {
            if (!is_string($date)) {
                continue;
            }

            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dt && $dt->format('Y-m-d') === $date) {
                $newDates[$date] = $dt;
            }
        }

        try {
            // Dates déjà en base
            $existingEntities = $pro->getAvailabilityDates()->toArray();
            $existingMap = [];

            foreach ($existingEntities as $entity) {
                $key = $entity->getAvailableDate()?->format('Y-m-d');
                if ($key) {
                    $existingMap[$key] = $entity;
                }
            }

            // 1) Supprimer uniquement celles décochées
            foreach ($existingMap as $dateString => $entity) {
                if (!isset($newDates[$dateString])) {
                    $em->remove($entity);
                }
            }

            // 2) Ajouter uniquement les nouvelles
            foreach ($newDates as $dateString => $dt) {
                if (!isset($existingMap[$dateString])) {
                    $availability = new TalentAvailability();
                    $availability->setProfessionalProfile($pro);
                    $availability->setAvailableDate($dt);
                    $em->persist($availability);
                }
            }

            // 3) Mettre à jour le statut
            $wantedLabel = count($newDates) > 0 ? 'Disponible' : 'Indisponible';

            $status = $em->getRepository(StatusProfile::class)
                ->createQueryBuilder('s')
                ->andWhere('LOWER(s.status) = :st')
                ->setParameter('st', mb_strtolower($wantedLabel))
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$status) {
                return $this->json([
                    'ok' => false,
                    'message' => 'Le statut "' . $wantedLabel . '" n’existe pas en base.'
                ], 500);
            }

            $pro->setStatusProfile($status);

            $em->flush();

            return $this->json([
                'ok' => true,
                'dates' => array_keys($newDates),
                'statusLabel' => $wantedLabel,
                'badgeClass' => count($newDates) > 0 ? 'text-bg-success' : 'text-bg-danger',
                'message' => count($newDates) > 0
                    ? 'Vos disponibilités ont été enregistrées.'
                    : 'Aucune date sélectionnée. Vous êtes indisponible.'
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'message' => 'Impossible d’enregistrer les disponibilités.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/talent-availability-calendar/list', name: 'talent_availability_calendar_list', methods: ['GET'])]
    #[IsGranted('ROLE_TALENT')]
    public function list(TalentAvailabilityRepository $availabilityRepository): JsonResponse
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Non autorisé.'], 401);
        }

        $pro = $user->getProfessionalProfile();

        if (!$pro) {
            return $this->json(['ok' => false, 'message' => 'Profil professionnel introuvable.'], 404);
        }

        return $this->json([
            'ok' => true,
            'dates' => $availabilityRepository->findDateStringsByProfile($pro),
        ]);
    }
}
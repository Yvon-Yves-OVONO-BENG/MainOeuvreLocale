<?php

namespace App\Controller\Web\Rating;

use App\Entity\ProfessionalProfile;
use App\Entity\Rating;
use App\Entity\User;
use App\Repository\RatingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RatingController extends AbstractController
{
    #[Route('/ajax/talents/{id}/rate', name: 'ajax_talent_rate', methods: ['POST'])]
    public function rate(
        ProfessionalProfile $professionalProfile,
        Request $request,
        EntityManagerInterface $em,
        RatingRepository $ratingRepo
    ): JsonResponse {

        $ratingBy = $this->getUser();

        // 1) Must be logged in
        if (!$ratingBy instanceof User) {
            return $this->json([
                'ok' => false,
                'code' => 'AUTH_REQUIRED',
                'message' => 'Veuillez vous connecter pour voter.',
                'loginUrl' => $this->generateUrl('app_login'),
            ], 401);
        }

        // 2) Ajax only (optionnel mais pro)
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['ok' => false, 'message' => 'Bad request'], 400);
        }

        // ✅ La personne notée = owner du profil pro
        $ratedUser = $professionalProfile->getUser();
        if (!$ratedUser instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Profil invalide.'], 404);
        }

        // 3) stars validation
        $stars = (int) $request->request->get('stars', 0);
        if ($stars < 1 || $stars > 5) {
            return $this->json(['ok' => false, 'message' => 'Note invalide'], 422);
        }

        // 4) Empêcher de se noter soi-même
        if ($ratedUser->getId() === $ratingBy->getId()) {
            return $this->json(['ok' => false, 'message' => 'Vous ne pouvez pas vous noter vous-même.'], 403);
        }

        // 5) Déjà voté ? (ratingBy -> ratedUser)
        $existing = $ratingRepo->findUserVoteForUser((int) $ratingBy->getId(), (int) $ratedUser->getId());
        if ($existing) {
            $stats = $ratingRepo->getStatsForUser((int) $ratedUser->getId());

            return $this->json([
                'ok' => true,
                'already_voted' => true,
                'message' => 'Vous avez déjà voté pour ce profil.',
                'avg' => (float) ($stats['avg'] ?? 0),
                'voters' => (int) ($stats['voters'] ?? 0),
            ]);
        }

        // 6) Persister
        try {
            $rating = new Rating();
            $rating->setRatingBy($ratingBy);        // ✅ celui qui vote
            $rating->setUser($ratedUser);           // ✅ celui qui reçoit la note
            $rating->setStars($stars);
            $rating->setCreatedAt(new \DateTimeImmutable());

            $em->persist($rating);
            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Double clic / race condition -> déjà voté
            $stats = $ratingRepo->getStatsForUser((int) $ratedUser->getId());

            return $this->json([
                'ok' => true,
                'already_voted' => true,
                'message' => 'Vous avez déjà voté pour ce profil.',
                'avg' => (float) ($stats['avg'] ?? 0),
                'voters' => (int) ($stats['voters'] ?? 0),
            ]);
        }

        $stats = $ratingRepo->getStatsForUser((int) $ratedUser->getId());

        return $this->json([
            'ok' => true,
            'already_voted' => false,
            'message' => 'Merci pour votre vote.',
            'avg' => (float) ($stats['avg'] ?? 0),
            'voters' => (int) ($stats['voters'] ?? 0),
        ]);
    }
}

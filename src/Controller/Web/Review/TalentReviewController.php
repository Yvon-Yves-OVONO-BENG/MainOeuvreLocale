<?php

namespace App\Controller\Web\Review;

use App\Entity\Review;
use App\Entity\User;
use App\Repository\ProfessionalProfileRepository;
use App\Service\AdminContentModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TalentReviewController extends AbstractController
{
    #[Route('/talent/{id}/review/new', name: 'talent_review_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        int $id,
        Request $request,
        ProfessionalProfileRepository $professionalProfileRepository,
        EntityManagerInterface $em,
        AdminContentModerationService $moderation,
    ): JsonResponse {
        // ✅ On force l'AJAX (optionnel mais conseillé)
        if (!$request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'message' => 'Requête invalide (AJAX requis).'
            ], 400);
        }

        $profile = $professionalProfileRepository->find($id);
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Talent introuvable.'
            ], 404);
        }

        /**
         * @var User
         */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Vous devez être connecté.'
            ], 401);
        }

        // ✅ Déterminer l'utilisateur ciblé par l'avis (targetReview)
        // On suppose que ton ProfessionalProfile a getUser()
        if (!method_exists($profile, 'getUser') || !$profile->getUser() instanceof User) {
            return $this->json([
                'success' => false,
                'message' => "Impossible de déterminer l'utilisateur du talent."
            ], 422);
        }
        $targetUser = $profile->getUser();

        // ✅ GET : renvoyer le mini formulaire HTML
        if ($request->isMethod('GET')) {
            $html = $this->renderView('review/_form_ajax.html.twig', [
                'professionalProfile' => $profile,
                'postUrl' => $this->generateUrl('talent_review_new', ['id' => $profile->getId()]),
            ]);

            return $this->json([
                'success' => true,
                'html' => $html
            ]);
        }

        // ✅ POST : enregistrer l'avis
        $comment = trim((string) $request->request->get('comment', ''));

        if (mb_strlen($comment) < 3) {
            return $this->json([
                'success' => false,
                'message' => 'Votre avis est trop court (min 3 caractères).'
            ], 422);
        }

        $assessment = $moderation->assessUserText($comment);
        if (!$assessment['allowed']) {
            return $this->json(['success' => false, 'message' => 'Cet avis contient un contenu inapproprié ou assimilé à du spam.'], 422);
        }

        // 🔒 Anti “auto-avis”
        if ($targetUser === $user) {
            return $this->json([
                'success' => false,
                'message' => "Vous ne pouvez pas vous laisser un avis à vous-même."
            ], 403);
        }

        $review = new Review();
        $review->setTargetBy($user);              // auteur
        $review->setTargetReview($targetUser);    // cible
        $review->setComment($comment);
        $review->setCreatedAt(new \DateTime());
        if (method_exists($review, 'setPublished') && $assessment['requiresModeration']) $review->setPublished(false);

        $em->persist($review);
        $em->flush();

        // ✅ Option: renvoyer un HTML d’un item avis, pour l’ajouter direct à la liste
        $reviewItemHtml = $this->renderView('review/_item.html.twig', [
            'review' => $review
        ]);

        return $this->json([
            'success' => true,
            'message' => 'Avis envoyé ✅',
            'reviewHtml' => $reviewItemHtml
        ], 201);
    }
}

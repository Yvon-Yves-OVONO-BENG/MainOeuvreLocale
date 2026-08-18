<?php

namespace App\Controller\Web\Favorites;

use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/favorites', name: 'app_favorites_')]
final class RemoveTalentFavoriteController extends AbstractController
{
    #[Route('/{id}/remove', name: 'remove', methods: ['POST'])]
    public function remove(
        Favorite $favorite,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        // Sécurité : on ne supprime que SES favoris
        if ($favorite->getUser()?->getId() !== $me->getId()) {
            return $this->json(['ok' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('fav_remove_'.$favorite->getId(), $token)) {
            return $this->json(['ok' => false, 'message' => 'Action expirée, réessaie.'], 400);
        }

        $em->remove($favorite);
        $em->flush();

        return $this->json(['ok' => true, 'message' => 'Retiré de vos favoris.']);
    }
}
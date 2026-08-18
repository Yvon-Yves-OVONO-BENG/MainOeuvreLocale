<?php

namespace App\Controller\Web\Favorites;

use App\Entity\Favorite;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class FavoriteController extends AbstractController
{
    #[Route('/favorites/user/{id}/toggle', name: 'favorite_toggle_user', methods: ['POST'])]
    public function toggleUserFavorite(
        User $targetUser,
        Request $request,
        EntityManagerInterface $em,
        FavoriteRepository $favoriteRepository
    ): JsonResponse {
        // ✅ Sécurité : user connecté
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        // ✅ Optionnel : n’autoriser que AJAX
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['ok' => false, 'message' => 'Bad request'], 400);
        }

        // ✅ CSRF (recommandé pour POST)
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('favorite_toggle_user_'.$targetUser->getId(), $token)) {
            return $this->json(['ok' => false, 'message' => "Action refusée. Veuillez réessayer."], 400);
        }

        // ✅ Empêcher de se mettre soi-même en favori (optionnel)
        if ($targetUser->getId() === $user->getId()) {
            return $this->json(['ok' => false, 'message' => 'Vous ne pouvez pas vous mettre en favori.'], 403);
        }

        // 🔎 Vérifier si déjà en favori
        $existing = $favoriteRepository->findOneBy([
            'user' => $user,
            'targetUser' => $targetUser,
        ]);

        // ❌ Si existe => on supprime
        if ($existing) {
            $em->remove($existing);
            $em->flush();

            return $this->json([
                'ok' => true,
                'favorited' => false,
                // ✅ compteur des gens qui ont mis CE user en favori
                'count' => $favoriteRepository->count(['targetUser' => $targetUser]),
            ]);
        }

        // ✅ Sinon => on crée
        $fav = new Favorite();
        $fav->setUser($user);
        $fav->setTargetUser($targetUser);
        $fav->setCreatedAt(new \DateTimeImmutable());

        $em->persist($fav);
        $em->flush();

        return $this->json([
            'ok' => true,
            'favorited' => true,
            'count' => $favoriteRepository->count(['targetUser' => $targetUser]),
        ]);
    }
}
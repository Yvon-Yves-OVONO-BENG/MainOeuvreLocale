<?php

namespace App\Controller\Web\Favorites;

use App\Entity\User;
use App\Repository\FavoriteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/favorites', name: 'app_favorites_')]
class FavoritedByController extends AbstractController
{
    #[Route('/favorited-by', name: 'favorited_by', methods: ['GET'])]
    public function favoritedBy(FavoriteRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $rows = $repo->findUsersWhoFavoritedMe($me);

        return $this->render('mes_favoris/favorited_by.html.twig', [
            'rows' => $rows, // chaque row = Favorite, avec row.user
        ]);
    }
}
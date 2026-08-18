<?php

namespace App\Controller\Web\Favorites;

use App\Entity\User;
use App\Repository\FavoriteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/favorites', name: 'app_favorites_')]
final class FavoritesIndexController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(FavoriteRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $favorites = $repo->findAllForUser($me);

        return $this->render('mes_favoris/mes_favoris.html.twig', [
            'favorites' => $favorites,
        ]);
    }
}
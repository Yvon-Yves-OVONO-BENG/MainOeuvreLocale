<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FavoriteService
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected FavoriteRepository  $favoriteRepository
    )
    {}

    public function mesFavoris(User $user): int
    {
        $favoritesCount = $this->favoriteRepository->count([
            'user' => $user,
        ]);

        return $favoritesCount;
    }

    public function jeSuisFavori(User $user): int
    {
        $favoritedByCount = count($this->favoriteRepository->findUsersWhoFavoritedMe($user));
        
        return $favoritedByCount;
    }
}
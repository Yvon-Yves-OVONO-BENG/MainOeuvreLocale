<?php

namespace App\Service;

use App\Entity\FavoriJob;
use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class FavoriteRemover
{
    public function __construct(
        private EntityManagerInterface $em,
        private CsrfTokenManagerInterface $csrf,
    ) {}

    /**
     * @return array{ok:bool, code:int, message:string}
     */
    public function removeTalentFavorite(Favorite $favorite, User $user, string $submittedToken): array
    {
        // CSRF
        if (!$this->csrf->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken(
            'fav_talent_remove_'.$favorite->getId(),
            $submittedToken
        ))) {
            return ['ok' => false, 'code' => 400, 'message' => "Action refusée. Veuillez réessayer."];
        }

        // Ownership
        if ($favorite->getUser()?->getId() !== $user->getId()) {
            return ['ok' => false, 'code' => 403, 'message' => "Accès refusé."];
        }

        $this->em->remove($favorite);
        $this->em->flush();

        return ['ok' => true, 'code' => 200, 'message' => 'Retiré des favoris.'];
    }

    /**
     * @return array{ok:bool, code:int, message:string}
     */
    public function removeJobFavorite(FavoriJob $favoriJob, User $user, string $submittedToken): array
    {
        // CSRF
        if (!$this->csrf->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken(
            'fav_job_remove_'.$favoriJob->getId(),
            $submittedToken
        ))) {
            return ['ok' => false, 'code' => 400, 'message' => "Action refusée. Veuillez réessayer."];
        }

        // Ownership
        if ($favoriJob->getUser()?->getId() !== $user->getId()) {
            return ['ok' => false, 'code' => 403, 'message' => "Accès refusé."];
        }

        $this->em->remove($favoriJob);
        $this->em->flush();

        return ['ok' => true, 'code' => 200, 'message' => 'Retiré des favoris.'];
    }
}
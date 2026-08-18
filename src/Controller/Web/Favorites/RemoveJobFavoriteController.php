<?php

namespace App\Controller\Web\Favorites;

use App\Entity\FavoriJob;
use App\Entity\User;
use App\Service\FavoriteRemover;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class RemoveJobFavoriteController extends AbstractController
{
    #[Route('/favoris/job/{id}/remove', name: 'app_favorite_job_remove', methods: ['POST'])]
    public function __invoke(
        FavoriJob $favoriJob,
        FavoriteRemover $remover,
        Request $request
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'ok' => false,
                'message' => 'Non connecté.'
            ], 401);
        }

        $token = (string) $request->request->get('_token', '');

        $res = $remover->removeJobFavorite($favoriJob, $user, $token);

        return $this->json([
            'ok' => $res['ok'],
            'message' => $res['message']
        ], $res['code']);
    }
}
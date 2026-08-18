<?php

namespace App\Controller\Web\Ajax;

use App\Entity\FavoriJob;
use App\Repository\JobRepository;
use App\Repository\FavoriJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class FavoriJobAjaxController extends AbstractController
{
    #[Route('/ajax/job/{slug}/favorite-toggle', name: 'ajax_job_favorite_toggle', methods: ['POST'])]
    public function toggle(
        string $slug,
        Request $request,
        JobRepository $jobRepo,
        FavoriJobRepository $favRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // (option) CSRF
        // if (!$this->isCsrfTokenValid('fav_toggle', $request->headers->get('X-CSRF-TOKEN'))) {
        //     return $this->json(['ok' => false, 'message' => 'Token invalide'], 403);
        // }

        $job = $jobRepo->findOneBy(['slug' => $slug]);
        if (!$job) return $this->json(['ok' => false, 'message' => 'Job introuvable'], 404);

        $user = $this->getUser();

        $existing = $em->getRepository(FavoriJob::class)->findOneBy([
            'user' => $user,
            'job'  => $job
        ]);

        if ($existing) {
            $em->remove($existing);
            $em->flush();

            return $this->json([
                'ok' => true,
                'state' => 'removed',
                'favoritesCount' => $favRepo->countFavorites($job),
            ]);
        }

        $fav = new FavoriJob();
        $fav->setUser($user);
        $fav->setJob($job);
        $fav->setCreatedAt(new \DateTimeImmutable());

        $em->persist($fav);
        $em->flush();

        return $this->json([
            'ok' => true,
            'state' => 'added',
            'favoritesCount' => $favRepo->countFavorites($job),
        ]);
    }
}

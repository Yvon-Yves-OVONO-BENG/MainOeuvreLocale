<?php

declare(strict_types=1);

namespace App\Controller\Web\Job;

use App\Entity\Job;
use App\Entity\User;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BulkDeleteJobsController extends AbstractController
{
    #[Route('/mes-offres/suppression-multiple', name: 'job_bulk_delete', methods: ['POST'])]
    public function __invoke(
        Request $request,
        JobRepository $jobRepository,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non authentifié.'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'message' => 'Requête invalide.'], 400);
        }

        $token = (string) ($payload['_token'] ?? $request->headers->get('X-CSRF-TOKEN', ''));
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('bulk_delete_jobs', $token))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide. Rechargez la page.'], 403);
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, is_array($payload['ids'] ?? null) ? $payload['ids'] : []),
            static fn (int $id): bool => $id > 0,
        )));
        $ids = array_slice($ids, 0, 100);

        if ($ids === []) {
            return $this->json(['success' => false, 'message' => 'Aucune mission sélectionnée.'], 422);
        }

        $deleted = [];
        foreach ($jobRepository->findBy(['id' => $ids]) as $job) {
            if (!$job instanceof Job || !$this->canDelete($job, $user)) {
                continue;
            }

            $deleted[] = (int) $job->getId();
            $entityManager->remove($job);
        }

        if ($deleted === []) {
            return $this->json(['success' => false, 'message' => 'Aucune mission autorisée ne peut être supprimée.'], 403);
        }

        try {
            $entityManager->flush();
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'message' => 'Suppression impossible : certaines missions possèdent encore des données liées.',
            ], 409);
        }

        return $this->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => sprintf('%d mission(s) supprimée(s) avec succès.', count($deleted)),
        ]);
    }

    private function canDelete(Job $job, User $user): bool
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        foreach (['getUser', 'getOwner', 'getAuthor', 'getCreatedBy', 'getPublisher'] as $getter) {
            if (!method_exists($job, $getter)) {
                continue;
            }

            $owner = $job->{$getter}();
            if ($owner instanceof User) {
                return $owner->getId() === $user->getId();
            }

            if (is_object($owner) && method_exists($owner, 'getUser')) {
                $ownerUser = $owner->getUser();
                if ($ownerUser instanceof User) {
                    return $ownerUser->getId() === $user->getId();
                }
            }
        }

        foreach (['getProfessionalProfile', 'getPersonalProfile'] as $getter) {
            if (!method_exists($job, $getter)) {
                continue;
            }

            $profile = $job->{$getter}();
            if (is_object($profile) && method_exists($profile, 'getUser')) {
                $ownerUser = $profile->getUser();
                if ($ownerUser instanceof User) {
                    return $ownerUser->getId() === $user->getId();
                }
            }
        }

        return false;
    }
}

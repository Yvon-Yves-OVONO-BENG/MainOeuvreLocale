<?php

namespace App\Controller\Api\Job;

use App\Entity\Job;
use App\Entity\User;
use App\Form\JobType;
use App\Service\Job\JobManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AjouterJobController extends AbstractController
{
    #[Route('/api/jobs-new/{slug}', name: 'api_job_new', methods: ['POST'])]
    public function new(
        Request $request,
        JobManagerService $jobManagerService,
        string $slug = '',
    ): JsonResponse {
        try {
            $job = $jobManagerService->findOrCreate($slug);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $jobManagerService->initializeJob($job, $user);

        $briefSlug = trim((string) $request->query->get('brief', ''));
        $selectedBrief = $jobManagerService->getSelectedBrief($briefSlug);

        if ($selectedBrief && !$job->getId()) {
            $jobManagerService->applyBriefTemplate($job, $selectedBrief);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (isset($payload['title'])) {
            $job->setTitle($payload['title']);
        }
        if (isset($payload['description'])) {
            $job->setDescription($payload['description']);
        }
        if (array_key_exists('salaireMin', $payload)) {
            $job->setSalaireMin($payload['salaireMin']);
        }
        if (array_key_exists('salaireMax', $payload)) {
            $job->setSalaireMax($payload['salaireMax']);
        }
        if (isset($payload['experience'])) {
            $job->setExperience($payload['experience']);
        }
        if (isset($payload['mission'])) {
            $job->setMission($payload['mission']);
        }
        if (isset($payload['profil'])) {
            $job->setProfil($payload['profil']);
        }

        $salaryError = $jobManagerService->validateSalaryRange($job);
        if ($salaryError !== null) {
            return $this->json([
                'success' => false,
                'message' => $salaryError,
            ], 422);
        }

        $jobManagerService->save($job, $slug !== '');

        return $this->json([
            'success' => true,
            'message' => $slug !== '' ? 'Job mis à jour avec succès.' : 'Job enregistré avec succès.',
            'data' => [
                'id' => $job->getId(),
                'slug' => $job->getSlug(),
                'reference' => $job->getReference(),
                'title' => $job->getTitle(),
            ],
        ]);
    }
}
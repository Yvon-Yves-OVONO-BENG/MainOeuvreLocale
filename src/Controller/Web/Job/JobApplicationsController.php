<?php

namespace App\Controller\Web\Job;

use App\Entity\Job;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobApplicationsController extends AbstractController
{
    #[Route('/publisher-jobs-applications/{slug}', name: 'talent_job_applications', methods: ['GET'])]
    public function __invoke(
        Job $job,
        ApplicationRepository $applicationRepository,
        string $slug
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // 🔒 sécurité : seul le propriétaire du job peut voir
        if (method_exists($job, 'getUser') && $job->getCreatedBy() !== $me) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Marquer comme "vu" toutes les candidatures non vues de ce job
        $applicationRepository->markViewedForJob($job->getId());

        // Liste des candidatures (tu peux créer une méthode dédiée si tu veux)
       $apps = $applicationRepository->findByJobWithUser($job);

        return $this->render('job/applications.html.twig', [
            'job' => $job,
            'apps' => $apps,
        ]);
    }
}
<?php

namespace App\Controller\Web\Ajax;

use App\Entity\Application;
use App\Repository\JobRepository;
use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApplyJobAjaxController extends AbstractController
{
    #[Route('/ajax/jobs/{slug}/apply', name: 'ajax_job_apply', methods: ['POST'])]
    public function apply(
        string $slug,
        JobRepository $jobRepo,
        ApplicationRepository $appRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $job = $jobRepo->findOneBy(['slug' => $slug]);
        if (!$job) {
            return $this->json(['ok' => false, 'message' => 'Job introuvable.'], 404);
        }

        $user = $this->getUser();

        // empêcher double candidature
        $exists = $appRepo->findOneBy(['user' => $user, 'job' => $job]);
        if ($exists) {
            return $this->json([
                'ok' => true,
                'already' => true,
                'message' => 'Vous avez déjà postulé à cette offre.',
            ]);
        }

        $application = new Application();
        $application->setUser($user)
            ->setJob($job)
            ->setStatus(Application::STATUS_NEW)
            
            /* ✅ Génération référence unique */
            ->setReference('APP-' . strtoupper(bin2hex(random_bytes(3))));

        $em->persist($application);
        $em->flush();

        return $this->json([
            'ok' => true,
            'already' => false,
            'message' => 'Vous avez postulé avec succès.',
        ]);
    }

    #[Route('/ajax/jobs/{slug}/apply-cancel', name: 'ajax_job_apply_cancel', methods: ['POST'])]
    public function cancel(
        string $slug,
        JobRepository $jobRepo,
        ApplicationRepository $appRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $job = $jobRepo->findOneBy(['slug' => $slug]);
        if (!$job) {
            return $this->json(['ok' => false, 'message' => 'Job introuvable.'], 404);
        }

        $user = $this->getUser();

        $application = $appRepo->findOneBy(['user' => $user, 'job' => $job]);
        if (!$application) {
            return $this->json([
                'ok' => true,
                'already' => true,
                'message' => "Vous n'avez pas encore postulé à cette offre.",
            ]);
        }

        $em->remove($application);
        $em->flush();

        return $this->json([
            'ok' => true,
            'already' => false,
            'message' => "Candidature annulée avec succès.",
        ]);
    }

}

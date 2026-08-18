<?php

namespace App\Controller\Web\Ajax;

use App\Entity\ReportJob;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ReportJobAjaxController extends AbstractController
{
    #[Route('/ajax/jobs/{slug}/report', name: 'ajax_job_report', methods: ['POST'])]
    public function report(
        string $slug,
        Request $request,
        JobRepository $jobRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $job = $jobRepository->findOneBy(['slug' => $slug]);

        if (!$job) {
            return $this->json(['ok' => false, 'message' => 'Job introuvable.'], 404);
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        $reason = trim((string)($payload['reason'] ?? ''));

        if ($reason === '' || mb_strlen($reason) < 5) {
            return $this->json(['ok' => false, 'message' => 'La raison doit contenir au moins 5 caractères.'], 422);
        }

        $report = new ReportJob();
        $report->setJob($job);
        $report->setSlug(\App\Util\HashedSlugGenerator::generate());
        $report->setUser($this->getUser()); // peut être null si non connecté
        $report->setReason($reason);
        $report->setStatus('pending');
        $report->setCreatedAt(new \DateTimeImmutable());

        $em->persist($report);
        $em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Merci. Votre signalement a été envoyé.',
        ]);
    }
}

<?php

namespace App\Controller\Web\Company;

use App\Entity\Application;
use App\Entity\Job;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompanyPipelineController extends AbstractController
{
    #[Route('/company-pipeline', name: 'company_pipeline', methods: ['GET'])]
    public function index(
        Request $request,
        ApplicationRepository $appRepo,
        JobRepository $jobRepo,
    ): Response {
        /** @var User|null $company */
        $company = $this->getUser();
        if (!$company) return $this->redirectToRoute('app_login');

        // Filtres
        $period = (string) $request->query->get('period', '30'); // 7|30|90|custom
        $jobId  = (int) $request->query->get('job', 0);
        $q      = trim((string) $request->query->get('q', ''));
        $fromQ  = (string) $request->query->get('from', '');
        $toQ    = (string) $request->query->get('to', '');

        $tz = new \DateTimeZone('Africa/Douala');
        $today = new \DateTimeImmutable('today', $tz);

        if ($period === 'custom' && $fromQ && $toQ) {
            $from = (new \DateTimeImmutable($fromQ, $tz))->setTime(0, 0, 0);
            $to   = (new \DateTimeImmutable($toQ, $tz))->setTime(23, 59, 59);
        } else {
            $days = (int) $period;
            if (!in_array($days, [7,30,90], true)) $days = 30;
            $from = $today->sub(new \DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0, 0);
            $to   = $today->setTime(23, 59, 59);
            $period = (string) $days;
        }

        $jobsForSelect = $jobRepo->findCompanyJobsForSelect($company);

        $selectedJob = null;
        if ($jobId > 0) {
            $selectedJob = $jobRepo->findOneBy(['id' => $jobId, 'createdBy' => $company]);
        }

        $columns = $appRepo->findPipelineByCompany($company, $selectedJob, $from, $to, $q ?: null, 80);
        $counts  = $appRepo->countByStatusForCompany($company, $selectedJob, $from, $to);

        return $this->render('company/pipeline.html.twig', [
            'filters' => [
                'period' => $period,
                'jobId'  => $selectedJob?->getId() ?? 0,
                'from'   => $from->format('Y-m-d'),
                'to'     => $to->format('Y-m-d'),
                'q'      => $q,
            ],
            'jobsForSelect' => $jobsForSelect,
            'columns' => $columns,
            'counts' => $counts,
            'moveUrlTpl' => $this->generateUrl('company_pipeline_move', ['id' => '__ID__']),
        ]);
    }

    #[Route('/company-pipeline-move/{id}', name: 'company_pipeline_move', methods: ['POST'])]
    public function move(
        int $id,
        Request $request,
        ApplicationRepository $appRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $company */
        $company = $this->getUser();
        if (!$company) return $this->json(['ok' => false, 'message' => 'Non connecté'], 401);

        $payload = json_decode($request->getContent(), true) ?: [];
        $status = strtoupper(trim((string)($payload['status'] ?? '')));

        $allowed = [
            Application::STATUS_NEW,
            Application::STATUS_REVIEWED,
            Application::STATUS_SHORTLIST,
            Application::STATUS_ACCEPTED,
            Application::STATUS_REJECTED,
        ];
        if (!in_array($status, $allowed, true)) {
            return $this->json(['ok' => false, 'message' => 'Statut invalide'], 400);
        }

        /** @var Application|null $app */
        $app = $appRepo->find($id);
        if (!$app || !$app->getJob() || !$app->getJob()->getCreatedBy()) {
            return $this->json(['ok' => false, 'message' => 'Candidature introuvable'], 404);
        }

        // Sécurité : il faut que le job appartienne à l'entreprise connectée
        if ($app->getJob()->getCreatedBy()->getId() !== $company->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé'], 403);
        }

        $app->setStatus($status);

        // Marquer comme "consulté" si c'est la 1ère fois
        if ($app->getViewedAt() === null) {
            $app->setViewedAt(new \DateTimeImmutable('now', new \DateTimeZone('Africa/Douala')));
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'id' => $app->getId(),
            'status' => $app->getStatus(),
            'viewed' => $app->getViewedAt() !== null,
        ]);
    }
}
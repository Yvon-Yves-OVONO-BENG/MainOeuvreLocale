<?php

namespace App\Controller\Web\Moderateur;

use App\Repository\UserRepository;
use App\Repository\ReportRepository;
use App\Repository\ReportJobRepository;
use App\Repository\PersonalProfileRepository;
use App\Repository\ProfessionalProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/analytics', name: 'moderateur_analytics_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class AnalyticsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        UserRepository $userRepo,
        ReportRepository $reportRepo,
        ReportJobRepository $reportJobRepo,
        PersonalProfileRepository $ppRepo,
        ProfessionalProfileRepository $proRepo
    ): Response {
        $days = max(7, min(180, (int) $request->query->get('days', 30)));

        $data = [
            'days' => $days,

            // Users
            'newUsersDaily' => $userRepo->analyticsNewUsersDaily($days),
            'rolesCounts'   => $userRepo->analyticsRoleCounts($days),
            'genderCounts'  => $ppRepo->analyticsGenderCountsNewUsers($days),

            // Reports (accounts)
            'reportsDaily'  => $reportRepo->analyticsReportsDaily($days),
            'reportStatus'  => $reportRepo->analyticsStatusCounts($days),
            'topTargets'    => $reportRepo->analyticsTopTargets(10, $days),
            'topCategories' => $reportRepo->analyticsTopCategories(10, 30),

            // Reports (jobs)
            'jobReportsDaily' => $reportJobRepo->analyticsReportsDaily($days),
            'jobStatus'       => $reportJobRepo->analyticsStatusCounts($days),
            'topJobs'         => $reportJobRepo->analyticsTopJobs(10, 30),

            // Verification funnel
            'cniStats' => $ppRepo->getCniStats(),
            'cvStats'  => $proRepo->getCvStats(),
        ];

        return $this->render('moderateur/analytics.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(
        Request $request,
        UserRepository $userRepo,
        ReportRepository $reportRepo,
        ReportJobRepository $reportJobRepo,
        PersonalProfileRepository $ppRepo,
        ProfessionalProfileRepository $proRepo
    ): JsonResponse {
        $days = max(7, min(180, (int) $request->query->get('days', 30)));

        return $this->json([
            'ok' => true,
            'days' => $days,
            'newUsersDaily' => $userRepo->analyticsNewUsersDaily($days),
            'rolesCounts'   => $userRepo->analyticsRoleCounts($days),
            'genderCounts'  => $ppRepo->analyticsGenderCountsNewUsers($days),

            'reportsDaily'  => $reportRepo->analyticsReportsDaily($days),
            'reportStatus'  => $reportRepo->analyticsStatusCounts($days),
            'topTargets'    => $reportRepo->analyticsTopTargets(10, $days),
            'topCategories' => $reportRepo->analyticsTopCategories(10, 30),

            'jobReportsDaily' => $reportJobRepo->analyticsReportsDaily($days),
            'jobStatus'       => $reportJobRepo->analyticsStatusCounts($days),
            'topJobs'         => $reportJobRepo->analyticsTopJobs(10, 30),

            'cniStats' => $ppRepo->getCniStats(),
            'cvStats'  => $proRepo->getCvStats(),
        ]);
    }


    #[Route('/moderateur/analytics', name: 'user', methods: ['GET'])]
    public function analytics(ReportRepository $reportRepo): Response
    {
        $sla = $reportRepo->slaStats(14);

        return $this->render('moderateur/analytics_user.html.twig', [
            'sla' => $sla,
        ]);
    }
}
<?php

namespace App\Controller\Web\Company;

use App\Entity\Job;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\FavoriJobRepository;
use App\Repository\JobRepository;
use App\Repository\JobViewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompanyAnalyticsController extends AbstractController
{
    #[Route('/company_analytics', name: 'company_analytics', methods: ['GET'])]
    public function index(
        Request $request,
        JobRepository $jobRepo,
        JobViewRepository $jobViewRepo,
        ApplicationRepository $appRepo,
        FavoriJobRepository $favRepo,
    ): Response {
        /** @var User|null $company */
        $company = $this->getUser();
        if (!$company) {
            return $this->redirectToRoute('app_login');
        }

        // ✅ Filtres
        $period = (string) $request->query->get('period', '30'); // 7|30|90|custom
        $jobId  = (int) $request->query->get('job', 0);
        $fromQ  = (string) $request->query->get('from', '');
        $toQ    = (string) $request->query->get('to', '');

        // ✅ Période -> from/to
        $tz = new \DateTimeZone('Africa/Douala');
        $today = new \DateTimeImmutable('today', $tz);

        if ($period === 'custom' && $fromQ && $toQ) {
            $from = (new \DateTimeImmutable($fromQ, $tz))->setTime(0, 0, 0);
            $to   = (new \DateTimeImmutable($toQ, $tz))->setTime(23, 59, 59);
        } else {
            $days = (int) $period;
            if (!in_array($days, [7, 30, 90], true)) $days = 30;
            $from = $today->sub(new \DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0, 0);
            $to   = $today->setTime(23, 59, 59);
            $period = (string) $days; // normaliser
        }

        // ✅ Liste des jobs de l’entreprise (pour le select)
        $jobsForSelect = $jobRepo->findCompanyJobsForSelect($company);

        // ✅ Job filtré (optionnel)
        $selectedJob = null;
        if ($jobId > 0) {
            $selectedJob = $jobRepo->findOneBy(['id' => $jobId, 'createdBy' => $company]);
        }

        // ✅ KPIs
        $activeJobs = $jobRepo->countActiveJob($company, new \DateTimeImmutable('now', $tz));

        $views = $jobViewRepo->countViewsByCompanyPeriod($company, $from, $to, $selectedJob);
        $apps  = $appRepo->countApplicationsByCompanyPeriod($company, $from, $to, $selectedJob);
        $favs  = $favRepo->countFavoritesByCompanyPeriod($company, $from, $to, $selectedJob);

        // “Taux de réponse” = ici, on le calcule comme “taux de traitement” (candidatures vues / candidatures)
        $appsViewed = $appRepo->countViewedApplicationsByCompanyPeriod($company, $from, $to, $selectedJob);
        $replyRate = $apps > 0 ? (int) round(($appsViewed / $apps) * 100) : 0;

        // Temps moyen avant consultation (si viewedAt rempli)
        $avgFirstViewHours = $appRepo->avgHoursToFirstViewByCompanyPeriod($company, $from, $to, $selectedJob);

        $conversion = $views > 0 ? round(($apps / $views) * 100, 1) : 0.0;

        $kpis = [
            'activeJobs' => $activeJobs,
            'views' => $views,
            'apps' => $apps,
            'favorites' => $favs,
            'conversion' => $conversion, // %
            'replyRate' => $replyRate,   // %
            'avgFirstViewHours' => $avgFirstViewHours,
        ];

        // ✅ Séries pour graphe (daily views + daily apps)
        $viewsByDay = $jobViewRepo->dailyViewsByCompanyPeriod($company, $from, $to, $selectedJob);
        $appsByDay  = $appRepo->dailyApplicationsByCompanyPeriod($company, $from, $to, $selectedJob);

        // On remplit les jours manquants pour avoir des courbes propres
        $labels = [];
        $seriesViews = [];
        $seriesApps = [];
        for ($d = $from; $d <= $to; $d = $d->add(new \DateInterval('P1D'))) {
            $key = $d->format('Y-m-d');
            $labels[] = $d->format('d/m');
            $seriesViews[] = (int) ($viewsByDay[$key] ?? 0);
            $seriesApps[]  = (int) ($appsByDay[$key] ?? 0);
        }

        // ✅ Tableau performance par offre
        $rows = $jobRepo->getCompanyJobsPerformance($company, $from, $to, $selectedJob);
        // Ajouts calculés (conversion / reply)
        foreach ($rows as &$r) {
            $v = (int) $r['views'];
            $a = (int) $r['apps'];
            $av = (int) $r['appsViewed'];

            $r['conversion'] = $v > 0 ? round(($a / $v) * 100, 1) : 0.0;
            $r['replyRate'] = $a > 0 ? (int) round(($av / $a) * 100) : 0;
        }
        unset($r);

        // ✅ Breakdown statuts (optionnel mais très utile)
        $statusBreakdown = $appRepo->statusBreakdownByCompanyPeriod($company, $from, $to, $selectedJob);
        
        // ✅ Chart 2 : Donut statuts
        $statusLabels = ['NEW','REVIEWED','SHORTLIST','REJECTED','ACCEPTED'];
        $statusValues = [];
        foreach ($statusLabels as $sl) {
            $statusValues[] = (int)($statusBreakdown[$sl] ?? 0);
        }

        // ✅ Chart 3 : Top offres (Top 6)
        $topRows = array_slice($rows, 0, 6);
        $topLabels = [];
        $topViews = [];
        $topApps = [];
        $topConv = [];

        foreach ($topRows as $r) {
            $title = (string)($r['title'] ?? '');
            // mini trim pour éviter trop long en graphe
            $topLabels[] = mb_strlen($title) > 18 ? (mb_substr($title, 0, 18) . '…') : $title;

            $topViews[] = (int)($r['views'] ?? 0);
            $topApps[]  = (int)($r['apps'] ?? 0);
            $topConv[]  = (float)($r['conversion'] ?? 0.0);
        }
        return $this->render('company/analytics.html.twig', [
            'filters' => [
                'period' => $period,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'jobId' => $selectedJob?->getId() ?? 0,
            ],
            'jobsForSelect' => $jobsForSelect,
            'kpis' => $kpis,
            'chart' => [
                'labels' => $labels,
                'views' => $seriesViews,
                'apps' => $seriesApps,
            ],
            'rows' => $rows,
            'statusBreakdown' => $statusBreakdown,
            'selectedJob' => $selectedJob,

            'chartStatus' => [
                'labels' => $statusLabels,
                'values' => $statusValues,
            ],
            'chartTop' => [
                'labels' => $topLabels,
                'views'  => $topViews,
                'apps'   => $topApps,
                'conv'   => $topConv,
            ],
        ]);
    }
}
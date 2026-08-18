<?php
// src/Controller/Moderateur/RiskController.php

namespace App\Controller\Web\Moderateur;

use App\Repository\ReportRepository;
use App\Repository\ReportJobRepository;
use App\Repository\UserLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/risk', name: 'moderateur_risk_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class RiskController extends AbstractController
{
    public function __construct(private CsrfTokenManagerInterface $csrf) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        UserRepository $userRepo,
        UserLogRepository $logRepo,
        ReportRepository $reportRepo,
        ReportJobRepository $reportJobRepo
    ): Response {
        $days = max(7, min(90, (int)$request->query->get('days', 14)));
        $top  = max(5, min(30, (int)$request->query->get('top', 10)));

        // ✅ Stats (sans fonctions DQL)
        $logsStats   = $logRepo->riskStats($days, $top);
        $accStats    = $reportRepo->riskStats($days, $top);
        $jobStats    = $reportJobRepo->riskStats($days, $top);

        // ✅ KPI utiles
        $newUsers24h = $userRepo->countNewUsersSince(new \DateTimeImmutable('-24 hours'));

        // ✅ Construire une “file risque” (score simple)
        $openByUser  = $accStats['openByTarget'] ?? [];         // [userId => count]
        $pendByUser  = $jobStats['pendingByCreator'] ?? [];     // [userId => count]
        $lastSeen    = $logsStats['lastSeenByUser'] ?? [];      // [userId => 'Y-m-d H:i:s']

        $ids = array_unique(array_merge(array_keys($openByUser), array_keys($pendByUser), array_keys($lastSeen)));
        // ne garde que ceux avec un signal clair (au moins 1 report ou 1 job report)
        $ids = array_values(array_filter($ids, fn($id) => (($openByUser[$id] ?? 0) + ($pendByUser[$id] ?? 0)) > 0));

        $scored = [];
        foreach ($ids as $uid) {
            $open = (int)($openByUser[$uid] ?? 0);
            $pend = (int)($pendByUser[$uid] ?? 0);
            $seen = $lastSeen[$uid] ?? null;

            // score (ajuste si tu veux)
            $score = $open * 5 + $pend * 3;
            if ($seen) $score += 1;

            $scored[$uid] = [
                'userId' => (int)$uid,
                'score' => $score,
                'openReports' => $open,
                'pendingJobReports' => $pend,
                'lastSeen' => $seen,
            ];
        }

        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, 25);

        $riskUserIds = array_map(fn($r) => $r['userId'], $scored);
        $usersMap = $userRepo->findManyWithProfilesMap($riskUserIds); // [id => User]

        $queue = [];
        foreach ($scored as $row) {
            $u = $usersMap[$row['userId']] ?? null;
            if (!$u) continue;
            $queue[] = [
                'u' => $u,
                'openReports' => $row['openReports'],
                'pendingJobReports' => $row['pendingJobReports'],
                'lastSeen' => $row['lastSeen'],
                'score' => $row['score'],
            ];
        }

        return $this->render('moderateur/risk.html.twig', [
            'days' => $days,
            'top' => $top,

            'kpi' => [
                'newUsers24h' => $newUsers24h,
                'openReports' => $accStats['kpi']['open'] ?? 0,
                'pendingJobs' => $jobStats['kpi']['pending'] ?? 0,
                'logs24h'     => $logsStats['kpi']['total24h'] ?? 0,
                'uniqueIps24h'=> $logsStats['kpi']['uniqueIps24h'] ?? 0,
            ],

            'stats' => [
                'logs' => $logsStats,
                'accounts' => $accStats,
                'jobs' => $jobStats,
            ],

            'queue' => $queue,
            'csrfBlock' => fn(int $id) => $this->csrf->getToken('risk_block_'.$id)->getValue(),
        ]);
    }

    #[Route('/user/{id}/toggle-block', name: 'toggle_block', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function toggleBlock(int $id, Request $request, UserRepository $userRepo): Response
    {
        $token = (string)$request->request->get('_token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken('risk_block_'.$id, $token))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }

        $u = $userRepo->find($id);
        if (!$u) throw $this->createNotFoundException('Utilisateur introuvable.');

        $active = (bool)$u->isActive();
        $userRepo->setActiveById($id, !$active);

        $this->addFlash('success', $active ? 'Compte bloqué ✅' : 'Compte débloqué ✅');
        return $this->redirectToRoute('moderateur_risk_index');
    }
}
<?php

namespace App\Controller\Web\Admin;

use App\Entity\UserLog;
use App\Repository\UserLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit', name: 'admin_audit_')]
#[IsGranted('ROLE_ADMIN')]
class AdminAuditController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserLogRepository $repo): Response
    {
        $filters = [
            'q'      => trim((string) $request->query->get('q', '')),
            'action' => trim((string) $request->query->get('action', '')),
            'ip'     => trim((string) $request->query->get('ip', '')),
            'status' => trim((string) $request->query->get('status', '')), // connected/disconnected/all
            'from'   => trim((string) $request->query->get('from', '')),
            'to'     => trim((string) $request->query->get('to', '')),
            'sort'   => (string) $request->query->get('sort', 'logedAt'),
            'dir'    => strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];

        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset  = ($page - 1) * $perPage;

        $qb = $repo->qbSearch($filters);
        $total = (int) (clone $qb)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        $logs = $qb->setFirstResult($offset)->setMaxResults($perPage)->getQuery()->getResult();
        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/audit_index.html.twig', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
            'actionsList' => $repo->getDistinctActions(40),

            // mini KPI
            'kpi' => [
                'activeSessions' => $repo->countActiveSessions(),
                'todayConnections' => $repo->countTodayConnections(),
            ],
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(UserLog $log): Response
    {
        return $this->render('admin/audit_show.html.twig', ['log' => $log]);
    }

    #[Route('/export/csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request, UserLogRepository $repo): StreamedResponse
    {
        $filters = [
            'q'      => trim((string) $request->query->get('q', '')),
            'action' => trim((string) $request->query->get('action', '')),
            'ip'     => trim((string) $request->query->get('ip', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'from'   => trim((string) $request->query->get('from', '')),
            'to'     => trim((string) $request->query->get('to', '')),
            'sort'   => (string) $request->query->get('sort', 'logedAt'),
            'dir'    => strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];

        $qb = $repo->qbSearch($filters)->setMaxResults(5000);

        $response = new StreamedResponse(function () use ($qb) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','logedAt','user','ip','ville','country','device','os','browser','action','disconnectedAt','userAgent']);

            foreach ($qb->getQuery()->toIterable() as $log) {
                /** @var UserLog $log */
                fputcsv($out, [
                    $log->getId(),
                    $log->getLogedAt()?->format('Y-m-d H:i:s'),
                    $log->getUser()?->getEmail(),
                    $log->getIp(),
                    $log->getVille(),
                    $log->getCountry()?->getId(), // adapte si country a name
                    $log->getDeviceType()?->getId(),
                    $log->getOperatingSystem()?->getId(),
                    $log->getBrowser()?->getId(),
                    $log->getAction(),
                    $log->getDisconnectedAt()?->format('Y-m-d H:i:s'),
                    $log->getUserAgent(),
                ]);
            }
            fclose($out);
        });

        $filename = 'user_audit_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    #[Route('log', name: 'index', methods: ['GET'])]
    public function log(): Response
    {
        $logs = [
            [
                'event' => 'Connexion super admin',
                'actor' => 'admin@example.com',
                'level' => 'info',
                'createdAt' => new \DateTimeImmutable('-15 minutes'),
            ],
            [
                'event' => 'Réindexation moteur',
                'actor' => 'admin@example.com',
                'level' => 'warning',
                'createdAt' => new \DateTimeImmutable('-2 hours'),
            ],
            [
                'event' => 'Mode maintenance activé',
                'actor' => 'admin@example.com',
                'level' => 'critical',
                'createdAt' => new \DateTimeImmutable('-1 day'),
            ],
        ];

        return $this->render('admin/audit/audit.html.twig', [
            'logs' => $logs,
        ]);
    }
}
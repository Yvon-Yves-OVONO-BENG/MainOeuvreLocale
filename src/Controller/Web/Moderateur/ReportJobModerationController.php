<?php

namespace App\Controller\Web\Moderateur;

use App\Entity\ReportJob;
use App\Repository\ReportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/reports/jobs')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class ReportJobModerationController extends AbstractController
{
    #[Route('', name: 'moderateur_report_jobs_index', methods: ['GET'])]
    public function index(Request $request, ReportJobRepository $repo): Response
    {
        $status = $request->query->get('status'); // pending|reviewed|rejected|resolved|null
        $q      = trim((string) $request->query->get('q', ''));
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(50, max(10, (int) $request->query->get('limit', 12)));

        $result = $repo->findForModeration($status, $q, $page, $limit);
        $stats  = $repo->getDashboardStats(days: 14, top: 10);

        return $this->render('moderateur/report_jobs.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
            'q'     => $q,
            'status'=> $status,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'moderateur_report_jobs_show', methods: ['GET'])]
    public function show(ReportJob $r): Response
    {
        return $this->render('moderateur/report_jobs_show.html.twig', ['r' => $r]);
    }

    #[Route('/{id}/status/{status}', name: 'moderateur_report_jobs_set_status', methods: ['POST'])]
    public function setStatus(ReportJob $r, string $status, Request $request, EntityManagerInterface $em): Response
    {
        $allowed = ['pending','reviewed','rejected','resolved'];

        if (!in_array($status, $allowed, true)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('moderateur_report_jobs_show', ['id' => $r->getId()]);
        }

        if (!$this->isCsrfTokenValid('repjob_action_'.$r->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('moderateur_report_jobs_show', ['id' => $r->getId()]);
        }

        $r->setStatus($status);
        $em->flush();

        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirectToRoute('moderateur_report_jobs_show', ['id' => $r->getId()]);
    }
}
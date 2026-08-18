<?php

namespace App\Controller\Web\Moderateur;

use App\Entity\Report;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/reports')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class ReportModerationController extends AbstractController
{
    #[Route('', name: 'moderateur_reports_index', methods: ['GET'])]
    public function index(Request $request, ReportRepository $repo): Response
    {
        $status = $request->query->get('status'); // open|resolved|canceled|null
        $q      = trim((string) $request->query->get('q', ''));
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = min(50, max(10, (int) $request->query->get('limit', 12)));

        $result = $repo->findForModeration($status, $q, $page, $limit);

        // ✅ Stats + Charts (30j + 14j)
        $stats = $repo->getDashboardStats(days: 14, top: 10);

        return $this->render('moderateur/reports.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
            'q'     => $q,
            'status'=> $status,
            'stats' => $stats,
        ]);
    }

    #[Route('/{slug}/ouvrir', name: 'moderateur_reports_show', methods: ['GET'])]
    public function show(Report $report, ReportRepository $repo): Response
    {
        $related = $repo->findRecentByTarget($report->getTargetUser(), 8, excludeId: $report->getId());

        return $this->render('moderateur/reports_show.html.twig', [
            'r' => $report,
            'related' => $related,
        ]);
    }

    #[Route('/{id}/resolve', name: 'moderateur_reports_resolve', methods: ['POST'])]
    public function resolve(Report $report, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATEUR');

        if (!$this->isCsrfTokenValid('rep_action_'.$report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('moderateur_reports_show', ['slug' => $report->getSlug()]);
        }

        if ($report->getStatus() !== Report::STATUS_RESOLVED) {
            $report->resolve($this->getUser());
            $em->flush();
            $this->addFlash('success', 'Signalement marqué comme traité.');
        }

        return $this->redirectToRoute('moderateur_reports_show', ['slug' => $report->getSlug()]);
    }

    #[Route('/{id}/reopen', name: 'moderateur_reports_reopen', methods: ['POST'])]
    public function reopen(Report $report, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('rep_action_'.$report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('moderateur_reports_show', ['slug' => $report->getSlug()]);
        }

        if ($report->getStatus() !== Report::STATUS_OPEN) {
            $report->setStatus(Report::STATUS_OPEN);
            $report->setHandledAt(null);
            $report->setHandledBy(null);
            $em->flush();
            $this->addFlash('success', 'Signalement rouvert.');
        }

        return $this->redirectToRoute('moderateur_reports_show', ['slug' => $report->getSlug()]);
    }

    #[Route('/{id}/toggle-delete', name: 'moderateur_reports_toggle_delete', methods: ['POST'])]
    public function toggleDelete(Report $report, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('rep_action_'.$report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('moderateur_reports_show', ['slug' => $report->getSlug()]);
        }

        $report->setSupprimer(!$report->isSupprimer());
        $em->flush();

        $this->addFlash('success', $report->isSupprimer() ? 'Marqué “à supprimer”.' : 'Marquage “à supprimer” retiré.');
        return $this->redirectToRoute('moderateur_reports_show', ['slug' => $report->getSlug()]);
    }
}
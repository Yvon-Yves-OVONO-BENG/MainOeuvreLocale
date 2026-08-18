<?php

namespace App\Controller\Web\Admin;

use App\Entity\Report; // adapte si besoin
use App\Repository\ReportRepository;
use App\Repository\UserLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/moderation', name: 'admin_moderation_')]
#[IsGranted('ROLE_ADMIN')]
class AdminModerationController extends AbstractController
{
    #[Route('/queue', name: 'queue', methods: ['GET'])]
    public function queue(Request $request, ReportRepository $repo): Response
    {
        $filters = [
            'status' => trim((string) $request->query->get('status', 'open')),
            'type'   => trim((string) $request->query->get('type', '')),
            'q'      => trim((string) $request->query->get('q', '')),
            'sort'   => (string) $request->query->get('sort', 'createdAt'),
            'dir'    => strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        ];

        $page    = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset  = ($page - 1) * $perPage;

        $qb = $repo->qbSearch($filters);

        $total = (int) (clone $qb)
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/moderation_queue.html.twig', [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'perPage'   => $perPage,
            'filters'   => $filters,
            'typesList' => $repo->getDistinctTypes(40),
            'csrf'      => $this->container->get('security.csrf.token_manager')
                ->getToken('admin_moderation')
                ->getValue(),
        ]);
    }

    #[Route('/case/{id}/status', name: 'set_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function setStatus(Report $report, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_moderation', $csrf)) {
            return $this->json(['ok' => false, 'error' => 'CSRF invalide'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $status = (string) ($payload['status'] ?? '');

        if (!in_array($status, ['open','investigating','resolved','rejected'], true)) {
            return $this->json(['ok' => false, 'error' => 'Statut invalide'], 400);
        }

        $report->setStatus($status);
        $em->flush();

        return $this->json(['ok' => true, 'status' => $status]);
    }

    #[Route('/bulk', name: 'bulk', methods: ['POST'])]
    public function bulk(Request $request, ReportRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_moderation', $csrf)) {
            return $this->json(['ok' => false, 'error' => 'CSRF invalide'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $ids = $payload['ids'] ?? [];
        $status = (string) ($payload['status'] ?? '');

        if (!is_array($ids) || empty($ids)) return $this->json(['ok'=>false,'error'=>'Aucun élément'], 400);
        if (!in_array($status, ['investigating','resolved','rejected'], true)) return $this->json(['ok'=>false,'error'=>'Statut invalide'], 400);

        $reports = $repo->createQueryBuilder('r')->andWhere('r.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
        foreach ($reports as $r) { $r->setStatus($status); }

        $em->flush();
        return $this->json(['ok'=>true,'count'=>count($reports)]);
    }
}
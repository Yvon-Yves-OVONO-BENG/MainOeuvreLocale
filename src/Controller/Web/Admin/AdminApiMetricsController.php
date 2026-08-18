<?php

namespace App\Controller\Web\Admin;

use App\Repository\ApiEndpointMetricRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/api-metrics', name: 'admin_api_metrics_')]
#[IsGranted('ROLE_ADMIN')]
class AdminApiMetricsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ApiEndpointMetricRepository $apiEndpointMetricRepository
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15;

        $qb = $apiEndpointMetricRepository->createQueryBuilder('a')
            ->orderBy('a.metricDate', 'DESC')
            ->addOrderBy('a.hits', 'DESC');

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $totalItems = count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $this->render('admin/api_metrics/metrics.html.twig', [
            'metrics' => iterator_to_array($paginator),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasPrevious' => $page > 1,
                'hasNext' => $page < $totalPages,
                'previousPage' => $page > 1 ? $page - 1 : 1,
                'nextPage' => $page < $totalPages ? $page + 1 : $totalPages,
            ],
        ]);
    }
}
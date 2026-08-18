<?php

namespace App\Controller\Web\Admin;

use App\Repository\ApiLatencyLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/api-latency', name: 'admin_api_latency_')]
#[IsGranted('ROLE_ADMIN')]
class AdminApiLatencyController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ApiLatencyLogRepository $apiLatencyLogRepository): Response
    {
        $avgLatency24h = $apiLatencyLogRepository->getAverageLatencyLastHours(24);
        $maxLatency24h = $apiLatencyLogRepository->getMaxLatencyLastHours(24);
        $slowRequests24h = $apiLatencyLogRepository->countSlowRequestsLastHours(24, 500);
        $requests24h = $apiLatencyLogRepository->countRequestsLastHours(24);
        $latestLogs = $apiLatencyLogRepository->findLatestLogs(30);
        $latencySeries = $apiLatencyLogRepository->getLatencySeriesLastDays(7);
        $topSlowEndpoints = $apiLatencyLogRepository->getTopSlowEndpointsLastHours(24, 10);

        return $this->render('admin/api_latency/api_latency.html.twig', [
            'avgLatency24h' => $avgLatency24h,
            'maxLatency24h' => $maxLatency24h,
            'slowRequests24h' => $slowRequests24h,
            'requests24h' => $requests24h,
            'latestLogs' => $latestLogs,
            'latencySeries' => $latencySeries,
            'topSlowEndpoints' => $topSlowEndpoints,
        ]);
    }
}
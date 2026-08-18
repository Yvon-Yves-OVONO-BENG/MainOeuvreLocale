<?php

namespace App\Controller\Web\Admin;

use App\Repository\UptimeCheckRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/uptime', name: 'admin_uptime_')]
#[IsGranted('ROLE_ADMIN')]
class AdminUptimeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(UptimeCheckRepository $uptimeCheckRepository): Response
    {
        return $this->render('admin/uptime/uptime.html.twig', [
            'uptime30d' => $uptimeCheckRepository->getUptimePercentLastDays(30),
            'uptime24h' => $uptimeCheckRepository->getUptimePercentLastHours(24),
            'avgLatency24h' => $uptimeCheckRepository->getAverageLatencyLastHours(24),
            'failures24h' => $uptimeCheckRepository->countFailuresLastHours(24),
            'latestChecks' => $uptimeCheckRepository->findLatestChecks(20),
        ]);
    }
}
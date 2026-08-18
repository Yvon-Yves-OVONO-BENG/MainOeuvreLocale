<?php

namespace App\Controller\Web\Admin;

use App\Service\AdminAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/analytics', name: 'admin_analytics_')]
class AdminAnalyticsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        AdminAnalyticsService $adminAnalyticsService
    ): Response {
        $range = (string) $request->query->get('range', '30d');
        $segment = (string) $request->query->get('segment', 'global');

        $data = $adminAnalyticsService->build($range, $segment);

        return $this->render('admin/analytics/index.html.twig', $data);
    }
}
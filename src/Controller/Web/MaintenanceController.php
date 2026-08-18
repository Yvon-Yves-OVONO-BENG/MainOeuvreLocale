<?php

namespace App\Controller\Web;

use App\Service\MaintenanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MaintenanceController extends AbstractController
{
    #[Route('/maintenance', name: 'app_maintenance', methods: ['GET'])]
    public function index(MaintenanceService $maintenanceService): Response
    {
        $setting = $maintenanceService->getSetting();

        return $this->render('maintenance/maintenance.html.twig', [
            'title' => $setting->getMaintenanceTitle() ?: 'Maintenance en cours',
            'message' => $setting->getMaintenanceMessage() ?: 'Nous revenons très bientôt.',
        ], new Response('', 503));
    }
}
<?php

namespace App\Controller\Web\Admin;

use App\Service\MaintenanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/platform', name: 'admin_platform_')]
#[IsGranted('ROLE_ADMIN')]
class AdminMaintenanceController extends AbstractController
{
    #[Route('/toggle-maintenance', name: 'toggle_maintenance', methods: ['POST'])]
    public function toggleMaintenance(
        Request $request,
        MaintenanceService $maintenanceService
    ): Response {
        if (!$this->isCsrfTokenValid('toggle_maintenance', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('tableau_de_bord');
        }

        $enabled = $maintenanceService->toggle();

        if ($enabled) {
            $this->addFlash('warning', 'Le site est maintenant en maintenance.');
        } else {
            $this->addFlash('success', 'Le site est de nouveau accessible.');
        }

        return $this->redirectToRoute('tableau_de_bord');
    }
}
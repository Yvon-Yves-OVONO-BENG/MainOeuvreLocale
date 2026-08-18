<?php

namespace App\Controller\Web\Admin;

use App\Service\AdminSecurityScoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/security-posture', name: 'admin_security_posture_')]
#[IsGranted('ROLE_ADMIN')]
class AdminSecurityPostureController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(AdminSecurityScoreService $adminSecurityScoreService): Response
    {
        $securityPosture = $adminSecurityScoreService->build();

        return $this->render('admin/security_posture/security_posture.html.twig', [
            'securityPosture' => $securityPosture,
        ]);
    }
}